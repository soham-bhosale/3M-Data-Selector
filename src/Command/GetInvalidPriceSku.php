<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Common\Entity\Row;
error_reporting (E_ALL ^ E_NOTICE);

class GetInvalidPriceSku extends Command{

    protected static $defaultName = 'app:xlsx-price';

    public function __construct()
    {
        parent::__construct();
    }


    public function configure(){

        $this->setDescription('Get brand names from xml file')
             ->addArgument('inputFile', InputArgument::REQUIRED, 'XML File')
             ->addArgument('outputFile', InputArgument::OPTIONAL, 'Output File');

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output):int
    {
        //0,13
        $inputFilePath = $input->getArgument('inputFile');
        $outputFilePath = $input->getArgument('outputFile');

        $reader = ReaderEntityFactory::createXLSXReader();
        $reader->open($inputFilePath);

        $writer = WriterEntityFactory::createXLSXWriter();
        $writer->openToFile($outputFilePath);

        $count = 0;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->getCells();
                $opRow = array();
                if($cells[13]->getValue() == "N/A"){
                    $count += 1;
                    $opRow[] = $cells[0]->getValue();
                    print_r($opRow);
                    $singleRow = WriterEntityFactory::createRowFromArray($opRow);
                    $writer->addRow($singleRow);
                }
            }
        }
        echo "\n".$count."\n";
        $writer->close();
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
                if ($index = array_search($attrLabel, $attrs)) {
                    $attrIndex[] = $index;
                }else{
                    if($attrs[0]===$attrLabel){
                        $attrIndex[] = $index;
                    }else{
                        $attrIndex[] = $attrLabel;
                    }
                }
            }
        }
        
        return $attrIndex;
    }
}