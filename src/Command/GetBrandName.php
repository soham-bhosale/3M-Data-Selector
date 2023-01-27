<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Console\Style\SymfonyStyle;

error_reporting (E_ALL ^ E_NOTICE);

class GetBrandName extends Command{

    protected static $defaultName = 'app:xml-brands';

    public function __construct()
    {
        parent::__construct();
    }


    public function configure(){

        $this->setDescription('Get brand names from xml file')
             ->addArgument('exportPath', InputArgument::OPTIONAL, 'XML File');

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output):int
    {
        echo "Started---\n";
        $io = new SymfonyStyle($input, $output);
        
        $brandData = $this->getSMBrandDetails();
        
        
        $dir = opendir("resources/individual_files");
        $f = new \FilesystemIterator("resources/individual_files");
        $f_count = iterator_count($f);
        $io->progressStart(100);
        $iter_count = 0;
        $prog = 0;
        $outputFile = fopen(__DIR__ . "../../../output/b.csv", 'a');
        fputcsv($outputFile, ['SKU', 'BrandID', 'BrandName']);
        while ($file = readdir($dir)) {
            

            if ($file == '.' || $file == '..') {
                continue;
            }


            $sku = substr($file,0,-4);


            $crawler = $this->setCrawler(new Crawler(), $file);
            

            $crawler->each(function (Crawler $node) use($outputFile, $sku, $brandData) {
                $brandID = $node->filterXPath('//us:BrandId')->innerText();
                
                $sku = $this->getSku($node);
                $brandName = $brandData[$brandID];
                if(is_null($brandName)){
                    echo $sku."\n";
                }else{
                    $row = array($sku, (int)$brandID, $brandName);
                    fputcsv($outputFile, $row);
                }
            });
            $iter_count +=1;
            if($iter_count*100/$f_count>1){
                $io->progressAdvance();
                $iter_count = 0;
            }
        }
        closedir($dir);

        $io->progressFinish();
        echo "Completed\n";
        return Command::SUCCESS;
    }

    private function setCrawler(Crawler $crawler, $file){
        $crawler->addXmlContent(file_get_contents("/home/soham/Parser/xml_parser/resources/individual_files/".$file));
        $crawler->registerNamespace('oa', 'http://www.openapplications.org/oagis/9');
        $crawler->registerNamespace('us', 'http://www.ussco.com/oagis/0');
        $crawler = $crawler->filterXPath('//us:DataArea//us:ItemMaster//us:ItemMasterHeader');
        return $crawler;
    }

    public function getSMBrandDetails(){
        $brandData = array();

        $crawler = new Crawler();
        $crawler->addXmlContent(file_get_contents("/home/soham/Parser/xml_parser/resources/sync_master/data.xml"));
        $crawler->registerNamespace('oa', 'http://www.openapplications.org/oagis/9');
        $crawler->registerNamespace('us', 'http://www.ussco.com/oagis/0');
        $crawler = $crawler->filterXPath('//us:DataArea//us:PartyMaster//us:ChildParty');

        $crawler->each(function (Crawler $node) use(&$brandData) {
            $brandId = $node->filterXPath('//oa:PartyIDs//oa:ID')->innerText();
            $brandName = $node->filterXPath('//oa:Name')->innerText();
            $brandData[$brandId] = $brandName;
        });
        return $brandData;
    }

    public function getSku(Crawler $node){
        $prefix = "";
        $manufac_sku_num = "";
        $node->filterXPath('//oa:ItemID')->each(function (Crawler $node) use(&$prefix, &$manufac_sku_num) {
            if($node->attr('agencyRole')=="Prefix_Number"){
                $prefix = $node->text();
            }else if($node->attr('agencyRole')=="Manufacturer_Sku_Number"){
                $manufac_sku_num = $node->text();
            }
        });

        return $prefix . $manufac_sku_num;
    }

}


?>