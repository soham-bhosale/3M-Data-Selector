<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Common\Entity\Row;

error_reporting (E_ALL ^ E_NOTICE);

class GetXLSXAttributes extends Command
{

    protected static $defaultName = 'app:xlsx-attrs';

    public function __construct()
    {
        parent::__construct();
    }


    public function configure()
    {

        $this->setDescription('Get xlsx file with valid columns')
            ->addArgument('inputFile', InputArgument::REQUIRED, 'Data XLSX File')
            ->addArgument('lookupFile', InputArgument::REQUIRED, 'Attribute names CSV File')
            ->addArgument('outputFile', InputArgument::REQUIRED, 'Output File(XLSX) Path');

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $inputFilePath = $input->getArgument('inputFile');
        $attrFilePath = $input->getArgument('lookupFile');
        $outputFilePath = $input->getArgument('outputFile');

        $file = fopen($outputFilePath, 'a');
        fclose($file);

        $attrFile = fopen($attrFilePath, 'r');

        $reader = ReaderEntityFactory::createXLSXReader();

        $reader->open($inputFilePath);
        
        $attrIndexArr = $this->getAttributeArray($reader, $attrFile);
        $invalidArr = $this->getInvalidValues();

        $this->getSpecifiedValuesByIndex($reader, $outputFilePath, $attrIndexArr, $invalidArr);

        
        
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

    public function getSpecifiedValuesByIndex($reader, $outputFilePath, $attrIndexArr, $invalidArr){

        $opRow = array();
        $writer = WriterEntityFactory::createXLSXWriter();
        $writer->openToFile($outputFilePath);
        foreach ($reader->getSheetIterator() as $sheet) {
            $i = 0;
            $c = 1;
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->getCells();
                $opRow = array();
                if($i<1){
                    $i += 1;
                    continue;
                }
                foreach($attrIndexArr as $index){
                    if(gettype($index)=="string"){
                        if($c<2){
                            // echo $index . "\n";
                            $opRow[] = $index;
                        }else{
                            $opRow[] = "";
                        }
                    }else{
                            $val = $cells[$index]->getValue();
                            
                            if(array_search($val, $invalidArr)){
                                $val = '';
                            }
                            $opRow[] = $val;
                            
                        }
                        
                    }
                    
                    // print_r($opRow);
                    $singleRow = WriterEntityFactory::createRowFromArray($opRow);
                    echo "Added row-->".$c."\n";
                    $writer->addRow($singleRow);
                    $c += 1;
                
            }
            break;
        }
        
        $writer->close();
        // exit;

    }
}

?>