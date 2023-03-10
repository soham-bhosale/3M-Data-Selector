<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Common\Entity\Row;

error_reporting (E_ALL ^ E_NOTICE);

class GetXLSXValidSchema extends Command
{

    protected static $defaultName = 'app:xlsx-valid-attrs';

    public function __construct()
    {
        parent::__construct();
    }


    public function configure()
    {
        $this->setDescription('Get brand names from xml file')
            ->addArgument('lookupFile', InputArgument::REQUIRED, 'LookUp CSV File')
            ->addArgument('dataFile', InputArgument::REQUIRED, "Data XLSX file")
            ->addArgument('outputFileName', InputArgument::OPTIONAL, 'Output File(CSV) Path');

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $lookupFilePath = $input->getArgument('lookupFile');        //csv
        $dataFilePath = $input->getArgument('dataFile');            //xlsx
        $outputFilePath = $input->getArgument('outputFileName');    //xlsx


        $lookupFile = fopen($lookupFilePath, 'r');
        $outputFile = fopen($outputFilePath, 'a');

        $reader = ReaderEntityFactory::createXLSXReader();

        $reader->open($dataFilePath);

        $lookupFile = fopen($lookupFilePath, 'r');
        $attrIndexArr = $this->getAttributeArray($reader, $lookupFile);
        $inValArr = $this->getInvalidValues();

        $validAttrs = array();
        $invalidAttrs = array();

        foreach($attrIndexArr as $index){
            if($this->validator($reader, $index, $inValArr)){
                $validAttrs[] = $index;
            }else{
                $invalidAttrs[] = $index;
            }
        }

        $validAttributesArr = $this->getLookUpArray($reader, $validAttrs, $lookupFilePath);
        $invalidAttributesArr = $this->getLookUpArray($reader, $invalidAttrs, $lookupFilePath);

        fputcsv($outputFile, ["Attribute Code", "Attribute Label"]);
        foreach($validAttributesArr as $key=>$val){
            fputcsv($outputFile, [$key, $val]);
        }
        echo "Invalid columns are:-\n";
        print_r($invalidAttributesArr);

        return Command::SUCCESS;
    }


    public function getAttributeArray($reader, $attrFile){
        foreach ($reader->getSheetIterator() as $sheet) {
            $rowN = 1;
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->getCells();
                if($rowN == 2){
                    $attrs = (function ($cells){
                        $i = 0;
                        $arr = array();
                        while($i<count($cells)){
                            array_push($arr, $cells[$i]->getValue());
                            $i+=1;
                        }
            
                        return $arr;
                    })($cells);
                    break;
                }
                $rowN += 1;
            }
            break;
        }
        
        $attrIndex = array();
        $header = fgetcsv($attrFile);
        while (!feof($attrFile)) {
            if ($row = fgetcsv($attrFile)) {
                $attrLabel = $row[1];
                $index = array_search($attrLabel, $attrs);
                if (!($index == "")) {
                    $attrIndex[] = $index;
                }else{
                    if($attrs[0]===$attrLabel){
                        $attrIndex[] = 0;
                    }else{
                        $attrIndex[] = $attrLabel;
                    }
                }
            }
        }
        return $attrIndex;
    }

    public function validator($reader, $index, $invalidArr){
        $isValid = false;

        foreach ($reader->getSheetIterator() as $sheet) {
            $i = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->getCells();
                if($i<2){
                    $i += 1;
                    continue;
                }
                if(gettype($index)=="string"){
                    return true;
                }
                // echo $index . "---".$element."---".array_search($element, $invalidArr)."\n";
                $element = $cells[$index]->getValue();
                if(array_search($element, $invalidArr)){
                    $isValid = false;
                }else{
                    $isValid = true;
                    break;
                }
            }
            break;
        }
        return $isValid;
    }

    public function getInvalidValues(){
        $invalidators = fopen('resources/3M/SchemaValidation/invalidValues.csv', 'r');
        $inVal = array();
        $inVal[] = "$-$-$-$-$-$-$-$-$";
        $inVal[] = '';
        while($elem = fgetcsv($invalidators)){
            $inVal[] = $elem[0];
        }

        return $inVal;
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

    public function getLookUpArray($reader, $valArr, $lookupFilePath){
        $lookupArr = $this->getCodeArr($lookupFilePath);

        foreach ($reader->getSheetIterator() as $sheet) {
            $rowN = 1;
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->getCells();
                if($rowN == 2){
                    $attrLabels = (function ($cells){
                        $i = 0;
                        $arr = array();
                        while($i<count($cells)){
                            array_push($arr, $cells[$i]->getValue());
                            $i+=1;
                        }
            
                        return $arr;
                    })($cells);
                    break;
                }
                $rowN += 1;
            }
            break;
        }

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
}
