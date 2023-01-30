<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Console\Style\SymfonyStyle;

error_reporting (E_ALL ^ E_NOTICE);

class GetAttributes extends Command
{

    protected static $defaultName = 'app:xml-attributes';

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
        $dirName = $input->getArgument('fileDirectory');
        $opFileName = fopen($input->getArgument('outputFileName'),'a');

        
        $dir = opendir($dirName);
        while ($file = readdir($dir)) {


            if ($file == '.' || $file == '..') {
                continue;
            }

            $crawler = $this->setCrawler(new Crawler, $file);

            if ($this->getProductClass($crawler) == "EYE CARE & PROTECTION") {
                try {
                    //code...
                    $this->getAttributes($crawler, $opFileName, $file);
                } catch (\Throwable $th) {
                    echo $file;
                    throw $th;
                }
            }

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

    public function getAttributes(Crawler $node, $outputFileName, $file){
        $node = $node->filterXPath("//oa:Specification//oa:Property");
        $node->each(function (Crawler $node) use ($outputFileName, $file) {
                if ($node->attr('sequence') > 0) {
                    $priority = $node->filterXPath("//oa:UserArea//us:FilterableNavigationPriority")->innerText();
                    // echo $priority . "\n";
                    if ($priority > 0) {
                        $attr = $node->filterXPath("//oa:NameValue")->attr('name');
                        $value = $node->filterXPath("//oa:NameValue")->innerText();
                        $sku = $this->getSku($file);
                        // echo $sku . "---" . $attr . "---" . $value . "---" . $priority."\n";
                        fputcsv($outputFileName, [$sku, $attr, $value, $priority]);
                    }
                // print_r($node);
                }
            });
        // echo $this->getSku($file);
    }

    public function getSku($file){
        $crawler = new Crawler();
        $crawler->addXmlContent(file_get_contents("/home/soham/Parser/xml_parser/resources/individual_files/".$file));
        $crawler->registerNamespace('oa', 'http://www.openapplications.org/oagis/9');
        $crawler->registerNamespace('us', 'http://www.ussco.com/oagis/0');
        $crawler->filterXPath('//us:DataArea//us:ItemMaster//us:ItemMasterHeader');
        $prefix = "";
        $stock_num_butted = "";
        $crawler->filterXPath('//oa:ItemID')->each(function (Crawler $node) use(&$prefix, &$stock_num_butted) {
            if($node->attr('agencyRole')=="Prefix_Number"){
                $prefix = $node->extract(['_text']);
                $prefix = trim($prefix[0]);
            }else if($node->attr('agencyRole')=="Stock_Number_Butted"){
                $stock_num_butted = $node->extract(['_text']);
                $stock_num_butted = trim($stock_num_butted[0]);
            }
        });
        return $prefix.$stock_num_butted;
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
}

?>