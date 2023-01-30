<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Console\Style\SymfonyStyle;


class GetJSON extends Command
{

    protected static $defaultName = 'app:get-json';

    public function __construct()
    {
        parent::__construct();
    }


    public function configure()
    {

        $this->setDescription('Get group product json file from csv files')
            ->addArgument('productFileName', InputArgument::REQUIRED, 'CSV File')
            ->addArgument('sourceFileName', InputArgument::REQUIRED, 'CSV File')
            ->addArgument('outputFileName', InputArgument::REQUIRED, 'CSV File');

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $indiProducts = $input->getArgument('productFileName');
        $groupData = $input->getArgument('sourceFileName');
        $opFile = $input->getArgument('outputFileName');

        $prodfile = fopen($indiProducts,'r');
        $opJsonFile = fopen($opFile, 'a');
        fwrite($opJsonFile,  "{\"items\":[");
        $finalprodArr = array();
        while(!feof($prodfile)) {
            $grpfile = fopen($groupData, 'r');
            $prodDataRow = fgetcsv($prodfile);
            if (gettype($prodDataRow) == "boolean"){
                continue;
            }
            $prodSku = $prodDataRow[0];
            while(!feof($grpfile)){
                $grpDataRow = fgetcsv($grpfile);
                if (gettype($grpDataRow) == "boolean"){
                    continue;
                }
                $grpProdSku = $grpDataRow[0];

                if($prodSku == $grpProdSku){

                    $prodArr = array(
                        "sku" => $grpDataRow[2],
                        "link_type" => "associated",
                        "linked_product_sku" => $grpProdSku,
                        "linked_product_type" => "simple",
                        "position" => $grpDataRow[3],
                        "extension_attributes" => array(
                            "qty" => 1
                        )
                    );
                    $finalprodArr[] = $prodArr;
                    fwrite($opJsonFile, json_encode($prodArr).",");
                }
            }
        }

        fwrite($opJsonFile, "]}");
        return Command::SUCCESS;
    }

}