<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;

error_reporting (E_ALL ^ E_NOTICE);

class GetCSVAttributes extends Command
{

    protected static $defaultName = 'app:xml-csv-attrs';

    public function __construct()
    {
        parent::__construct();
    }


    public function configure()
    {

        $this->setDescription('Get brand names from xml file')
            ->addArgument('inputFile', InputArgument::REQUIRED, 'Data CSV File')
            ->addArgument('lookupFile', InputArgument::REQUIRED, 'Attribute names CSV File')
            ->addArgument('outputFile', InputArgument::REQUIRED, 'Output File Path');

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $inputFilePath = $input->getArgument('inputFile');
        $attrFilePath = $input->getArgument('lookupFile');
        $outputFilePath = $input->getArgument('outputFile');

        $file = fopen($inputFilePath, 'r');
        $attrFile = fopen($attrFilePath, 'r');
        $outputFile = fopen($outputFilePath, 'a');

        fputcsv($outputFile, $this->setHeaders($attrFilePath));

        $attrIndexArr = $this->getAttributeArray($file, $attrFile);

        while($row = fgetcsv($file)){

            $opRow = $this->getSpecifiedValuesByIndex($row, $attrIndexArr);

            fputcsv($outputFile, $opRow);
        }

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
                if (!($index == "") || $index==0) {
                    $attrIndex[] = $index;
                }else{
                    $attrIndex[] = -1;
                }
            }
        }

        return $attrIndex;
    }

    public function getSpecifiedValuesByIndex($row, $attrIndexArr){

        $opRow = array();

        foreach($attrIndexArr as $index){
            if($index>-1){
                $opRow[] = $row[$index];
                
            }else{
                $opRow[] = "";
            }
        }

        return $opRow;
    }

    public function setHeaders($attrFilePath){
        $labels = array();
        $attrs = fopen($attrFilePath, 'r');
        $header = fgetcsv($attrs);
        while($row = fgetcsv($attrs)){
            $labels[] = $row[1];
        }

        return $labels;
    }
}

?>