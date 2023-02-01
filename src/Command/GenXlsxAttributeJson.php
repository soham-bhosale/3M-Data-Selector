<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Common\Entity\Row;
use Symfony\Component\Console\Question\ChoiceQuestion;

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

        $this->setDescription('Get brand names from xml file')
            ->addArgument('inputFile', InputArgument::REQUIRED, 'Data XLSX File')
            ->addArgument('outputFile', InputArgument::REQUIRED, 'Output File(JSON) Path');

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $inputFilePath = $input->getArgument('inputFile');
        $outputFilePath = $input->getArgument('outputFile');
        $addToLookup = array();
        $lookupFile = fopen("resources/local/PIMLookup.csv", 'r');
        $outputFile = fopen($outputFilePath, 'a');

        $group = "specification";
        
        $reader = ReaderEntityFactory::createXLSXReader();
        
        $reader->open($inputFilePath);

        
        $attrArr = $this->getAttributeArray($reader);
        $lookupArr = $this->getPIMLookupArray($lookupFile);
        
        foreach($attrArr as $attr){
            $useable_as_grid_filter = false;
            $wysiwyg_enabled = false;
            $decimals_allowed = false;
            $negative_allowed = false;
            $pimtype = $this->genJsonType($attr, $lookupArr, $input, $output, $addToLookup);
            $code = $this->getPIMCode($attr);

            if($pimtype == "pim_catalog_textarea"){
                $wysiwyg_enabled = true;
            }
            if($pimtype == "pim_catalog_number"){
                $decimals_allowed = true;
                $negative_allowed = true;
            }

            $json = "{\"code\":\"" . $code . "\",\"type\":\"" . $pimtype."\"".",\"group\":\"".$group."\"";
            $json .= ",\"useable_as_grid_filter\":" . "true";
            if($wysiwyg_enabled){
                $json .= ",\"wysiwyg_enabled\":" . "true";
            }
            if($decimals_allowed){
                $json .= ",\"decimals_allowed\":" . "false";
            }
            if($negative_allowed){
                $json .= ",\"negative_allowed\":" . "false";
            }
            $json .= ",\"labels\":{\"en_US\":\"".$attr."\"}}\n";

            echo $json."\n\n";

            fwrite($outputFile, $json);
        }

        fclose($lookupFile);
        $this->addNewPimTypesToLookUp($addToLookup);
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
        $pimtype = "";
        if($index = array_search($attr, $lookupCols)){
            $pimtype = $lookupArr[$attr];
        }else{
            echo "No exisiting PIM type found for column " . $attr . ". Enter manually\n";
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
        $code = strtolower($attr);                  // to lowercase
        $code = str_replace(" ", "_", $code);      //Replaces Spaces With Underscore
        $code = preg_replace('/[.]/','_',$code);  //Replaces . With Underscore
        $code = preg_replace('/[\/]/','_',$code);  //Replaces Forward Slash with Underscore
        $code = preg_replace('/["]/','in',$code);  //Replaces " with in
        $code = preg_replace('/[^A-Za-z0-9\_]/','',$code);   //Removes All Characters Except Alphanumeric and Underscore

        echo $code."\n";
        return $code;
    }

    public function addNewPimTypesToLookUp($addToLookup){
        $lookupFile = fopen("resources/local/PIMLookup.csv", 'a');

        foreach($addToLookup as $attr=>$pimtype){
            fputcsv($lookupFile, [$attr, $pimtype]);
        }

        fclose($lookupFile);
    }
}