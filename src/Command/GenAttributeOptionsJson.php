<?php

namespace App\Command;

use Akeneo\Pim\ApiClient\AkeneoPimClientBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Common\Entity\Row;
use Symfony\Component\Console\Question\ChoiceQuestion;

error_reporting (E_ALL ^ E_NOTICE);

class GenAttributeOptionsJson extends Command
{

    protected static $defaultName = 'app:gen-attrs-options-json';

    public function __construct()
    {
        parent::__construct();
    }


    public function configure()
    {

        $this->setDescription('Generate attribute options json and push to akeneo pim api')
            ->addArgument('inputFile', InputArgument::REQUIRED, 'Data XLSX File')
            ->addArgument('outputFile', InputArgument::REQUIRED, 'Output File(JSON) Path')
            ->addOption('push', null, InputOption::VALUE_OPTIONAL, 'Push attribute options to akeneo api');

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $inputFilePath = $input->getArgument('inputFile');
        $outputFilePath = $input->getArgument('outputFile');
        $lookupFile = fopen("resources/local/PIMLookup.csv", 'r');
        $outputFile = fopen($outputFilePath, 'a');


        $reader = ReaderEntityFactory::createXLSXReader();
        
        $reader->open($inputFilePath);

        $attrArr = $this->getAttributeArray($reader);
        $lookupArr = $this->getPIMLookupArray($lookupFile);
        fclose($lookupFile);
        $optionsAttrs = $this->getOptionsAttrs($attrArr, $lookupArr);

        foreach($optionsAttrs as $attr){
            $optionsApiArr = array();
            $options = $this->getUniqueOptions($attr, $reader, $attrArr);
            $attribute = $this->getPIMCode($attr);
            $optionsApiArr[0] = $attribute;
            foreach($options as $option){
                $fracLabel = $this->getFracLabel($option);
                $code = $this->getPIMCode($fracLabel);
                $optionsApiArr[1] = $code;
                $json = "{\"code\":\"" . $code."\"".",\"attribute\":\"".$attribute."\"".",\"labels\":{\"en_US\":\"".addslashes($fracLabel)."\"}}\n";
                $optionsApiArr[2]["labels"] = ["en_US" => $fracLabel];
                if($optionsApiArr[1]!=""){
                    if($input->getOption('push')==1){
                        $this->pushToOptionsApi($optionsApiArr);
                    }
                }
                fwrite($outputFile, $json);
            }

        }

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

    public function getOptionsAttrs($attrArr, $lookupArr){
        $lookupCols = array_keys($lookupArr);
        $pimtype = "";
        $optionsArr = array();
        foreach($attrArr as $attr){
            if($index = array_search($attr, $lookupCols)){
                $pimtype = $lookupArr[$attr];
                if($pimtype == "pim_catalog_simpleselect" || $pimtype == "pim_catalog_multiselect"){
                    $optionsArr[] = $attr;
                }
            } else {
                echo "No lookup value is available for attribute " . $attr.". It has been omitted from options generation";
            }
        }

        return $optionsArr;
    }

    public function getUniqueOptions($attr, $reader, $attrArr){
        $unique = array();
        foreach ($reader->getSheetIterator() as $sheet) {
            $i = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->getCells();
                if($i<=1){
                    $i += 1;
                    continue;
                }
                $element = $cells[array_search($attr, $attrArr)]->getValue();
                if(!(in_array($element, $unique))){
                    $unique[] = $element;
                }
            }
        }
        return $unique;
    }
    public function getPIMCode($attr){
        $code = strtolower(trim($attr));                  // to lowercase
        $code = str_replace(" ", "_", $code);      //Replaces Spaces With Underscore
        $code = preg_replace('/[.]/','_',$code);  //Replaces . With Underscore
        $code = preg_replace('/[\/]/','_',$code);  //Replaces Forward Slash with Underscore
        $code = preg_replace('/["]/','in',$code);  //Replaces " with in
        if($num = preg_match('/.+\++$/', $code, $match)){ //replaces number ending with + to num plus
            $code = str_replace('+', '_plus',$code);
            echo $code."\n";
            return $code;
        }
        $code = preg_replace('/[^A-Za-z0-9\_]/','',$code);   //Removes All Characters Except Alphanumeric and Underscore
        $code = preg_replace('/_+/', '_', $code);
        return $code;
    }

    public function pushToOptionsApi($optionsApiArr){
        $clientBuilder = new AkeneoPimClientBuilder('http://dev-cicero-pim.humcommerce.com/');
        $client = $clientBuilder->buildAuthenticatedByPassword('3_49vm8m4u1eassko8kgsc4kk8w8ww0kgcwwsoos0g400048csgw', '1p6muqkbd8jocg80o0o8g4s48gcwkkgc08cwg80848o0wswkw8', '3mattributes_9120', 'd1396acd9');

        try{
            $client->getAttributeOptionApi()->create($optionsApiArr[0],$optionsApiArr[1], $optionsApiArr[2]);
            echo "Pushed options to api\n";
        }catch(\Exception $e){
            print_r($e->getMessage());
            echo "Option already exists.\n";
        }
    }

    public function getFracLabel($label){

        if($n = preg_match('/(\sin)$/',$label, $mat)){
            $nLabel = str_replace($mat[0],"\"", $label);
            echo $nLabel."      ";
            $label = $nLabel;
        }
        
        if($num = preg_match('/\d+\.\d+/', $label, $match)){
            $nLabel = self::float2fraction((float)$match[0]).substr($label,strpos($label, $match[0])+strlen($match[0]));
            echo $nLabel."\n";
            return $nLabel;
        }
        
        return $label;
    }

    public function float2rat($n, $tolerance = 1.e-10) {
        $h1=1; $h2=0;
        $k1=0; $k2=1;
        $b = 1/$n;
        do {
            $b = 1/$b;
            $a = floor($b);
            $aux = $h1; $h1 = $a*$h1+$h2; $h2 = $aux;
            $aux = $k1; $k1 = $a*$k1+$k2; $k2 = $aux;
            $b = $b-$a;
        } while (abs($n-$h1/$k1) > $n*$tolerance);
      
        return "$h1/$k1";
      }
      
    public function float2fraction($float, $concat = ' '){
        
        // ensures that the number is float, 
        // even when the parameter is a string
        $float = (float)$float;
      
        if($float == 0 ){
          return $float;
        }
        
        // when float between -1 and 1
        if( $float > -1 && $float < 0  || $float < 1 && $float > 0 ){
          $fraction = self::float2rat($float);
          return $fraction;
        }
        else{
      
          // get the minor integer
          if( $float < 0 ){
            $integer = ceil($float);
          }
          else{
            $integer = floor($float);
          }
      
          // get the decimal
          $decimal = $float - $integer;
      
          if( $decimal != 0 ){
      
            $fraction = self::float2rat(abs($decimal));
            $fraction = $integer . $concat . $fraction;
            return $fraction;
          }
          else{
            return $float;
          }
        }
      }
}