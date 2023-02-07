<?php

namespace App\Command;

use Akeneo\Pim\ApiClient\AkeneoPimClientBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Common\Entity\Row;
use Symfony\Component\Console\Question\ChoiceQuestion;
require_once '/home/soham/Parser/xml_parser/vendor/autoload.php';

error_reporting (E_ALL ^ E_NOTICE);

class GenXlsxAttributeJson extends Command
{

    protected static $defaultName = 'app:gen-attrs-json';

    public function __construct()
    {
        parent::__construct();
    }


    public function configure()
    {

        $this->setDescription('Generate attrobute json and push to akeneo pim api')
            ->addArgument('inputFile', InputArgument::REQUIRED, 'Data XLSX File')
            ->addArgument('outputFile', InputArgument::REQUIRED, 'Output File(JSON) Path')
            ->addOption('attrPush','a', InputOption::VALUE_OPTIONAL, 'Pushes attribute to akeneo api',0)
            ->addOption('famPush','f',InputOption::VALUE_OPTIONAL, "Pushes family to akeneo api",0);

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $inputFilePath = $input->getArgument('inputFile');
        $outputFilePath = $input->getArgument('outputFile');
        $addToLookup = array();
        $lookupFile = fopen("/home/soham/Parser/xml_parser/resources/local/PIMLookup.csv", 'r');
        $outputFile = fopen($outputFilePath, 'a');

        $group = "specification";
        
        $reader = ReaderEntityFactory::createXLSXReader();
        
        $reader->open($inputFilePath);

        
        $attrArr = $this->getAttributeArray($reader);
        $lookupArr = $this->getPIMLookupArray($lookupFile);
        $familyAttrs = array();
        $familyApiArr = array();
        
        foreach($attrArr as $attr){
            if($attr == "3M ID"){
                continue;
            }
            $wysiwyg_enabled = false;
            $decimals_allowed = false;
            $negative_allowed = false;
            $pimtype = $this->genJsonType($attr, $lookupArr, $input, $output, $addToLookup);
            $code = $this->getPIMCode($attr);
            $familyAttrs[] = $code;
            $attrApiArr = array($code, []);
            $attrApiArr[1]["type"] = $pimtype;
            $attrApiArr[1]["group"] = $group;

            if($pimtype == "pim_catalog_textarea"){
                $wysiwyg_enabled = true;
            }
            if($pimtype == "pim_catalog_number"){
                $decimals_allowed = true;
                $negative_allowed = true;
            }

            $json = "{\"code\":\"" . $code . "\",\"type\":\"" . $pimtype."\"".",\"group\":\"".$group."\"";
            $json .= ",\"useable_as_grid_filter\":" . "true";
            $attrApiArr[1]["useable_as_grid_filter"] = true;
            if($wysiwyg_enabled){
                $json .= ",\"wysiwyg_enabled\":" . "true";
                $attrApiArr[1]["wysiwyg_enabled"] = true;
            }
            if($decimals_allowed){
                $json .= ",\"decimals_allowed\":" . "false";
                $attrApiArr[1]["decimals_allowed"] = false;

            }
            if($negative_allowed){
                $json .= ",\"negative_allowed\":" . "false";
                $attrApiArr[1]["negative_allowed"] = false;
            }
            $json .= ",\"labels\":{\"en_US\":\"".$attr."\"}}\n";
            $attrApiArr[1]["labels"] = ["en_US"=>$attr];

            echo "Generated json for ".$attr."\n";

            // print_r($attrApiArr);
            if($input->getOption('attrPush')==1){
                $this->pushToAttrsApi($attrApiArr);
            }

            fwrite($outputFile, $json);
        }

        fclose($lookupFile);
        $this->addNewPimTypesToLookUp($addToLookup);
        fwrite($outputFile, "\n".$this->generateFamilyForAttributes($familyAttrs, $input, $output, $familyApiArr))."\n";
        return Command::SUCCESS;
    }

    public function getAttributeArray($reader)
    {
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->getCells();
                    $attrs = (function ($cells) {
                    $i = 0;
                    $arr = array();
                    while ($i < count($cells)) {
                        array_push($arr, $cells[$i]->getValue());
                        $i += 1;
                    }
                    return $arr;
                })($cells);
                break;
            }
            break;
        }
        return $attrs;
    }

    public function getPIMLookupArray($lookupFile){
        $arr = array();
        while($row = fgetcsv($lookupFile)){
            $arr[$row[0]] = $row[1];
        }

        return $arr;
    }

    public function genJsonType($attr, $lookupArr, $input, $output, &$addToLookUp){
        $lookupCols = array_keys($lookupArr);
        $io = new SymfonyStyle($input, $output);
        $pimtype = "";
        if($index = array_search($attr, $lookupCols)){
            $pimtype = $lookupArr[$attr];
        }else{
            echo "No exisiting PIM type found for column \n";
            $io->caution($attr);
            echo "Enter manually\n";
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Please select pim type (defaults to pim_catalog_text)',
                ['pim_catalog_text', 'pim_catalog_textarea', 'pim_catalog_simpleselect',
                 'pim_catalog_multiselect','pim_catalog_date','pim_catalog_image','pim_catalog_number',
                 'pim_catalog_file','pim_catalog_boolean'],
                0
            );
            $question->setErrorMessage('PIM type %s is invalid.');

            $pimtype = $helper->ask($input, $output, $question);
            $output->writeln('Selected: '.$pimtype);
            $addToLookUp[$attr] = $pimtype;
        }
        return $pimtype;
    }

    public function getPIMCode($attr){
        $code = strtolower(trim($attr));                  // to lowercase
        $code = str_replace(" ", "_", $code);      //Replaces Spaces With Underscore
        $code = preg_replace('/[.]/','_',$code);  //Replaces . With Underscore
        $code = preg_replace('/[\/]/','_',$code);  //Replaces Forward Slash with Underscore
        $code = preg_replace('/["]/','in',$code);  //Replaces " with in
        $code = preg_replace('/[^A-Za-z0-9\_]/','',$code);   //Removes All Characters Except Alphanumeric and Underscore
        $code = preg_replace('/_+/', '_', $code);
        return $code;
    }

    public function addNewPimTypesToLookUp($addToLookup){
        $lookupFile = fopen("resources/local/PIMLookup.csv", 'a');

        foreach($addToLookup as $attr=>$pimtype){
            fputcsv($lookupFile, [$attr, $pimtype]);
        }

        fclose($lookupFile);
    }

    public function generateFamilyForAttributes($familyAttrs, $input, $output, &$familyApiArr){
        $io = new SymfonyStyle($input, $output);
        $inputFilePath = $input->getArgument('inputFile');
        $familyName = explode("/",$inputFilePath);
        $familyLabel = explode(".",$familyName[count($familyName) - 1])[0];
        $familyName = $this->getPIMCode($familyLabel);
        $io->note("Setting family name as input file name:- ".$familyName."\nAnd family label as:- ".$familyLabel);
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'Continue with this name?',
            ['Yes','No'],
            0
        );
        $question->setErrorMessage('PIM type %s is invalid.');
        $ans = $helper->ask($input, $output, $question);
        if ($ans == "No") {
            $question = new Question('Please enter the label name of the family (Default is \"'.$familyLabel."\" )\n -->", $familyLabel);
            $familyLabel = $helper->ask($input, $output, $question);
            $familyName = $this->getPIMCode($familyLabel);
            
        }

        echo "\nSetting family label is --> " . $familyLabel;
        echo "\nSetting family code is  --> " . $familyName."\n";

        $familyJson = "{\"code\":\"" . $familyName . "\",\"attributes\":[";
        $familyApiArr[0] = $familyName;
        $familyApiArr[1] =[];

        $sep = '';
        foreach($familyAttrs as $attr){
            $familyJson .= $sep . "\"" . $attr . "\"";
            $familyApiArr[1]["attributes"][] = $attr;
            $sep = ",";
        }
        $familyJson .= "],\"attribute_as_label\": \"".$familyAttrs[0]."\",";
        $familyApiArr[1]['attribute_as_label'] = $familyAttrs[0];

        $familyJson .= "\"labels\":{\"en_US\":\"" . $familyLabel . "\"}}\n";
        $familyApiArr[1]['labels'] = ["en_US" => $familyLabel];
        if($input->getOption('famPush')==1){
            $this->pushToFamilyApi($familyApiArr);
        }
        return $familyJson;
    }

    public function pushToAttrsApi($attrApiArr){
        
        $clientBuilder = new AkeneoPimClientBuilder('http://dev-cicero-pim.humcommerce.com/');
        $client = $clientBuilder->buildAuthenticatedByPassword('3_49vm8m4u1eassko8kgsc4kk8w8ww0kgcwwsoos0g400048csgw', '1p6muqkbd8jocg80o0o8g4s48gcwkkgc08cwg80848o0wswkw8', '3mattributes_9120', 'd1396acd9');

        $client->getAttributeApi()->upsert($attrApiArr[0], $attrApiArr[1]);
        echo "Pushed attribute to api\n";
    }

    public function pushToFamilyApi($familyApiArr){
        $clientBuilder = new AkeneoPimClientBuilder('http://dev-cicero-pim.humcommerce.com/');
        $client = $clientBuilder->buildAuthenticatedByPassword('3_49vm8m4u1eassko8kgsc4kk8w8ww0kgcwwsoos0g400048csgw', '1p6muqkbd8jocg80o0o8g4s48gcwkkgc08cwg80848o0wswkw8', '3mattributes_9120', 'd1396acd9');

        $client->getFamilyApi()->upsert($familyApiArr[0], $familyApiArr[1]);
        echo "Pushed family to api\n";

    }

}