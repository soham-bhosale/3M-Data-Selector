<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\DomCrawler\Crawler;


class GetBrandName extends Command{

    protected static $defaultName = 'app:xml-brands';

    public function __construct()
    {
        parent::__construct();
    }


    public function configure(){

        $this->setDescription('Get brand names from xml file')
             ->addArgument('fileName', InputArgument::OPTIONAL, 'XML File');

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $inputInterface, \Symfony\Component\Console\Output\OutputInterface $outputInterface):int
    {
        echo "In execute\n";

        $input = $inputInterface->getArgument('fileName');

        // if(strlen($input)<5){
        //     echo "Invalid file name";
        //     return 0;
        // } else {
            $crawler = new Crawler();
            $crawler->addXmlContent(file_get_contents('../data.xml'));
            $crawler->registerNamespace('oa', 'http://www.openapplications.org/oagis/9');
            $crawler->registerNamespace('us', 'http://www.ussco.com/oagis/0');
            $crawler = $crawler->filterXPath('//us:DataArea//us:PartyMaster//oa:Name');

            $crawler->each(function (Crawler $node) {
                $outputFile = fopen(__DIR__ . "../../../../brands.txt", 'a');
                $brand = $node->innerText() . ";\n";
                fwrite($outputFile, $brand);
            });
            echo "Completed execute";
            return 1;
        // }
    }
}


?>