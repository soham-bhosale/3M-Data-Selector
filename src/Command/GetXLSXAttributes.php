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

        $this->setDescription('Get brand names from xml file')
            ->addArgument('inputFile', InputArgument::REQUIRED, 'Data CSV File')
            ->addArgument('lookupFile', InputArgument::OPTIONAL, 'Attribute names CSV File')
            ->addArgument('outputFile', InputArgument::OPTIONAL, 'Output File Path');

    }

    public function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
    {
        $inputFilePath = $input->getArgument('inputFile');

        $reader = ReaderEntityFactory::createXLSXReader();

        $reader->open($inputFilePath);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->getCells();
            }
        }

        return Command::SUCCESS;

    }
}

?>