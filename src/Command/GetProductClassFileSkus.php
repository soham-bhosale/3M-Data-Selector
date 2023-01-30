<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Console\Style\SymfonyStyle;

error_reporting (E_ALL ^ E_NOTICE);

class GetProductClassFileSkus extends Command
{

    protected static $defaultName = 'app:product-class-name';

    public function __construct()
    {
        parent::__construct();
    }


    public function configure()
    {

        $this->setDescription('Get brand names from xml file')
            ->addArgument('fileDirectory', InputArgument::REQUIRED, 'XML File Directory')
            ->addArgument('outputFileName', InputArgument::REQUIRED, 'Output File Path');

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $dirPath = $input->getArgument('fileDirectory');
        $outputFilePath = $input->getArgument('outputFileName');

        $dir = opendir($dirPath);
        $outputFile = fopen($outputFilePath, 'a');

        while($file = readdir($dir)){
            if($file == '.' || $file=='..'){
                continue;
            }


            $crawler = $this->setCrawler(new Crawler(),$file);

            fputcsv($outputFile, [$this->getSku($crawler),$this->getProductClass($crawler)]);

        }

        return Command::SUCCESS;

    }

    private function setCrawler(Crawler $crawler, $file){
        $crawler->addXmlContent(file_get_contents("/home/soham/Parser/xml_parser/resources/individual_files/".$file));
        $crawler->registerNamespace('oa', 'http://www.openapplications.org/oagis/9');
        $crawler->registerNamespace('us', 'http://www.ussco.com/oagis/0');
        $crawler = $crawler->filterXPath('//us:DataArea//us:ItemMaster//us:ItemMasterHeader');
        return $crawler;
    }

    public function getProductClass(Crawler $crawler){
        $productClassName = '';

        $crawler->filterXPath('//us:Classification')->each(function (Crawler $node) use (&$productClassName) {
            if ($node->attr('type') == "Product_Class_Category") {
                $productClassName = $node->filterXPath('//us:Codes//oa:Code')->innerText();
            }
        });
        return $productClassName;
    }


    public function getSku(Crawler $node){
        $prefix = "";
        $manufac_sku_num = "";
        $node->filterXPath('//oa:ItemID')->each(function (Crawler $node) use(&$prefix, &$manufac_sku_num) {
            if($node->attr('agencyRole')=="Prefix_Number"){
                $prefix = $node->extract(['_text']);
                $prefix = trim($prefix[0]);
            }else if($node->attr('agencyRole')=="Stock_Number_Butted"){
                $manufac_sku_num = $node->extract(['_text']);
                $manufac_sku_num = trim($manufac_sku_num[0]);
            }
        });

        return $prefix . $manufac_sku_num;
    }
}

?>