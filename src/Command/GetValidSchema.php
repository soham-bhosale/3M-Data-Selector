<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;

error_reporting (E_ALL ^ E_NOTICE);

class GetValidSchema extends Command
{

    protected static $defaultName = 'app:schema-valid';

    public function __construct()
    {
        parent::__construct();
    }


    public function configure()
    {

        $this->setDescription('Get brand names from xml file')
            ->addArgument('lookupFile', InputArgument::REQUIRED, 'LookUp File')
            ->addArgument('dataFile', InputArgument::REQUIRED, "Data file")
            ->addArgument('invalidators', InputArgument::REQUIRED, 'Invalidate values\' file')
            ->addArgument('outputFileName', InputArgument::OPTIONAL, 'Output File Path');

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {

        $lookupFilePath = $input->getArgument('lookupFile');
        $dataFilePath = $input->getArgument('dataFile');
        $invalidatorFilePath = $input->getArgument('invalidators');
        $outputFilePath = $input->getArgument('outputFileName');

        $lookupFile = fopen($lookupFilePath, 'r');
        $dataFile = fopen($dataFilePath, 'r');
        $invalidators = fopen($invalidatorFilePath, 'r');
        $outputFile = fopen($outputFilePath, 'a');


        $inVal = $this->getInvalidValues($invalidators);

        $attrIndexArr = $this->getAttributeArray($dataFile, $lookupFile);
        $validAttrs = array();
        $invalidAttrs = array();
        fclose($dataFile);
        foreach($attrIndexArr as $index){
            if($this->validator($dataFilePath, $index, $inVal)){
                $validAttrs[] = $index;
            }else{
                $invalidAttrs[] = $index;
            }
        }


        $validAttributesArr = $this->getLookUpArray($dataFilePath, $validAttrs, $lookupFilePath);
        $invalidAttributesArr = $this->getLookUpArray($dataFilePath, $invalidAttrs, $lookupFilePath);

        fputcsv($outputFile, ["Attribute Code", "Attribute Label"]);
        foreach($validAttributesArr as $key=>$val){
            fputcsv($outputFile, [$key, $val]);
        }

        print_r($invalidAttributesArr);
        return Command::SUCCESS;
    }


    public function getAttributeArray($file, $attrFile){
        $attrGroups = fgetcsv($file);
        $attrs = fgetcsv($file);

        $attrIndex = array();
        $header = fgetcsv($attrFile);
        while (!feof($attrFile)) {
            if ($row = fgetcsv($attrFile)) {
                $attrLabel = $row[1];
                $index = array_search($attrLabel, $attrs);
                if (!($index == "")) {
                    $attrIndex[] = $index;
                }else{
                    if($index === 0){
                        $attrIndex[] = $index;
                    }else{
                        $attrIndex[] = $attrLabel;
                    }
                }
            }
        }
        fclose($attrFile);
        return $attrIndex;
    }

    public function validator($dataFilePath, $index, $invalidArr){
        $newdataFile = fopen($dataFilePath, 'r');
        $isValid = false;
        $headerGroup = fgetcsv($newdataFile);
        $headerAttrs = fgetcsv($newdataFile);

        while($row = fgetcsv($newdataFile)){

            if(gettype($index)=="string"){
                return true;
            }
            
            $element = $row[$index];

            // echo $index . "---".$element."---".array_search($element, $invalidArr)."\n";
            if(array_search($element, $invalidArr)){
                $isValid = false;
            }else{
                $isValid = true;
                break;
            }
        }
        fclose($newdataFile);
        return $isValid;
    }

    public function getInvalidValues($invalidators){
        $inVal = array();
        $inVal[] = "$-$-$-$-$-$-$-$-$";
        $inVal[] = '';
        while($elem = fgetcsv($invalidators)){
            $inVal[] = $elem[0];
        }

        return $inVal;
    }

    public function getLookUpArray($dataFilePath, $valArr, $lookupFilePath){
        $dataFile = fopen($dataFilePath,'r');
        $lookupArr = $this->getCodeArr($lookupFilePath);
        $headerGroups = fgetcsv($dataFile);
        $attrLabels = fgetcsv($dataFile);

        $labels = array();
        foreach ($valArr as $val) {
            if (gettype($val)=="string") {
                $code = array_search($val, $lookupArr);
                $labels[$code] = $val;
            } else {
                $code = array_search($attrLabels[$val], $lookupArr);
                $labels[$code] = $attrLabels[$val];
            }
        }

        return $labels;
    }


    public function getCodeArr($lookupFilePath){
        $lookupFile = fopen($lookupFilePath, 'r');
        $header = fgetcsv($lookupFile);
        $arr = array();
        while($row = fgetcsv($lookupFile)){
            $arr[$row[0]] = $row[1];
        }

        return $arr;
    }
}