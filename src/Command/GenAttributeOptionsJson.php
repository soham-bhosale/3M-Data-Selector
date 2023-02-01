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

class GenAttributeOptionsJson extends Command
{

    protected static $defaultName = 'app:gen-attrs-options-json';

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
        $lookupFile = fopen("resources/local/PIMLookup.csv", 'r');
        $outputFile = fopen($outputFilePath, 'a');


        $reader = ReaderEntityFactory::createXLSXReader();
        
        $reader->open($inputFilePath);

        $attrArr = $this->getAttributeArray($reader);
        $lookupArr = $this->getPIMLookupArray($lookupFile);
        fclose($lookupFile);
        $optionsAttrs = $this->getOptionsAttrs($attrArr, $lookupArr);

        foreach($optionsAttrs as $attr){
            $options = $this->getUniqueOptions($attr, $reader, $attrArr);
            $attribute = $this->getPIMCode($attr);
            foreach($options as $option){
                $code = $this->getPIMCode($option);
                $json = "{\"code\":\"" . $code."\"".",\"attribute\":\"".$attribute."\"".",\"labels\":{\"en_US\":\"".$option."\"}}\n";
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
        $code = strtolower($attr);                  // to lowercase
        $code = str_replace(" ", "_", $code);      //Replaces Spaces With Underscore
        $code = preg_replace('/[.]/','_',$code);  //Replaces . With Underscore
        $code = preg_replace('/[\/]/','_',$code);  //Replaces Forward Slash with Underscore
        $code = preg_replace('/["]/','in',$code);  //Replaces " with in
        $code = preg_replace('/[^A-Za-z0-9\_]/','',$code);   //Removes All Characters Except Alphanumeric and Underscore

        return $code;
    }
}