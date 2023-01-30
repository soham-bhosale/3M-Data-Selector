<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Console\Style\SymfonyStyle;


class GetGroupProductJson extends Command{

    protected static $defaultName = 'app:xml-group-json';

    public function __construct()
    {
        parent::__construct();
    }


    public function configure(){

        $this->setDescription('Get group product json file from xml files')
             ->addArgument('exportFileName', InputArgument::REQUIRED, 'XML File');

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output):int
    {   

        $outputFileName = $input->getArgument('exportFileName');
        $GLOBALS['skuArr'] = array();
        $dir = opendir("resources/individual_relationships");
        $outputFile = fopen(__DIR__ . "../../../output/".$outputFileName, 'a');
        fputcsv($outputFile, ['SKU', 'GroupName', 'GroupID','Position']);
        while ($file = readdir($dir)) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            echo $file . "\n";
            $crawler = $this->setCrawler(new Crawler(), $file);

            $crawler->each(function (Crawler $node) use ($outputFile) {
                $header = $node->filterXPath("//us:Name")->innerText();
                if ($header == "Product Family") {
                    $this->getGroupDetails($node, $outputFile);
                }
            });
        }

        return Command::SUCCESS;
    }

    private function setCrawler(Crawler $crawler, $file){
        $crawler->addXmlContent(file_get_contents("/home/soham/Parser/xml_parser/resources/individual_relationships/".$file));
        $crawler->registerNamespace('oa', 'http://www.openapplications.org/oagis/9');
        $crawler->registerNamespace('us', 'http://www.ussco.com/oagis/0');
        $crawler = $crawler->filterXPath('//us:DataArea//us:ProductRelationship//us:Relationship');
        return $crawler;
    }


    public function getGroupDetails(Crawler $node, $outputFile){
        $prefix = "";
        $stock_num_butted = "";
        $groupName = "";
        $groupId = "";
        $node->filterXPath('//us:RelationshipMember')->each(function (Crawler $node) use (&$prefix, &$stock_num_butted, &$groupName, &$groupId, $outputFile) {
            $prefix = $node->filterXPath("//us:PrefixNumber")->innerText();
            $stock_num_butted = $node->filterXPath("//us:StockNumberButted")->innerText();
            $position = $node->filterXPath("//us:Position")->innerText();
            $sku = $prefix . $stock_num_butted;

            $GroupCrawler = new Crawler();
            $GroupCrawler->addXmlContent(file_get_contents("resources/individual_files/".$sku.".xml"));
            $GroupCrawler->registerNamespace('oa', 'http://www.openapplications.org/oagis/9');
            $GroupCrawler->registerNamespace('us', 'http://www.ussco.com/oagis/0');
            

            $GroupCrawler->filterXPath("//us:DataArea//us:ItemMaster//us:ItemMasterHeader//us:Classification")->each(function ($node) use (&$groupName, &$groupId, $outputFile) {
                if ($node->attr('type') == "SKU_Group") {
                    $node->filterXPath("//us:Codes//oa:Code")->each(function (Crawler $node) use (&$groupName, &$groupId) {
                        if ($node->attr('name') == "SKU_Group_Name") {
                            $groupName = $node->extract(["_text"]);
                            $groupName = trim($groupName[0]);
                        } else if ($node->attr('name') == "SKU_Group_Id") {
                            $groupId = $node->extract(["_text"]);
                            $groupId = trim($groupId[0]);
                        }
                    });
                }
            });

            if(!in_array($sku,$GLOBALS['skuArr'])){
                $GLOBALS['skuArr'][] = $sku;
                $groupSku = $prefix . $groupId;
                fputcsv($outputFile, [$sku, $groupName, $groupSku, $position]);
            }
        });
    }

}


?>