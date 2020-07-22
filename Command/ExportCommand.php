<?php

namespace Basilicom\XmlToolBundle\Command;

use Basilicom\XmlToolBundle\Service\Xml;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends AbstractCommand
{

    private $xmlService;

    public function __construct(string $name = null, Xml $xmlService)
    {
        $this->xmlService = $xmlService;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('basilicom:treeexporter:export')
            ->setDescription('Exports a tree of objects')
            ->setHelp('Exports objects')
            ->addOption('xslt',null,InputOption::VALUE_REQUIRED, 'Apply specified XSL transformation file', false)
            ->addOption('file',null,InputOption::VALUE_REQUIRED, 'If set, write to this file', false)
            ->addOption('asset',null,InputOption::VALUE_REQUIRED, 'If set export to specified Pimcore Asset (full path)', false)
            ->addOption('root',null,InputOption::VALUE_REQUIRED, 'Use as root node name', false)
            ->addOption('omit-relation-object-fields', null, null, 'Do not export fields of related objects')
            ->addOption('include-variants', null, null, 'Export variants of object relations, too')
            ->addOption('raw', null, null, 'Do not make XML output human-readable (prettify/indentation)')
            ->addArgument(
                'objectPath',
                InputArgument::REQUIRED,
                'An object path'
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     *f
     * @example bin/console basilicom:xmltool:export <objectPath>
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $objectPath = $input->getArgument('objectPath');

        $targetFile = $input->getOption('file');
        $targetAsset = $input->getOption('asset');

        $output->writeln('Exporting tree of Objects starting at '.$objectPath);
        $object = DataObject::getByPath($objectPath);

        $root = $object->getKey();
        if ($root == '/') {
            $root = 'root';
        }

        if ($input->getOption('root')) {
            $root = $input->getOption('root');
        }

        $this->xmlService->setIncludeVariants($input->getOption('include-variants'));
        $this->xmlService->setOmitRelationObjectFields($input->getOption('omit-relation-object-fields'));

        $this->xmlService->setXslt($input->getOption('xslt'));

        $result = $this->xmlService->exportTree($object, $root);

        if (!$input->getOption('raw')) {
            $result->preserveWhiteSpace = false;
            $result->formatOutput = true;
        }

        $result = $result->saveXML();

        if ($targetFile) {
            file_put_contents($targetFile, $result);
        }

        if ($targetAsset) {
            $targetPath = dirname($targetAsset);
            $targetKey = basename($targetAsset);

            $asset = Asset::getByPath($targetAsset);
            if ($asset == null) {

                $parentFolder = Asset::getByPath($targetPath);
                if ($parentFolder == null) {
                    $parentFolder = Asset\Service::createFolderByPath($targetPath);
                }

                $asset = new Asset();
                $asset->setParent($parentFolder);
                $asset->setKey($targetKey);
            }
            $asset->setData($result);
            $asset->save();
        }

        // stdout
        if (($targetFile===false) && ($targetAsset===false))
        {
            $output->writeln($result);
        }
    }

}
