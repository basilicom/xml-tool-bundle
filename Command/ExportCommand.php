<?php

namespace Basilicom\XmlToolBundle\Command;

use Pimcore\Console\AbstractCommand;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Spatie\ArrayToXml\ArrayToXml;

class ExportCommand extends AbstractCommand
{

    public function __construct(string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('basilicom:treeexporter:export')
            ->setDescription('Exports a tree of objects')
            ->setHelp('Exports objects')
            ->addOption('file',null,InputOption::VALUE_REQUIRED, 'If set, write to this file', false)
            ->addOption('asset',null,InputOption::VALUE_REQUIRED, 'If set export as Pimcore Asset')
            ->addOption('omit-relation-object-attributes',null,null, 'Do not export attributes of related objects')
            ->addOption('omit-unsupported-field-types',null,null, 'Do not export attributes of related objects')
            ->addOption('include-variants',null,null, 'Export variants of object relations, too')
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

        $treeData = $this->exportObject($object);

        $arrayToXml = new ArrayToXml($treeData, $root);
        $result = $arrayToXml->prettify()->toXml();


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

    private function addChildToXml($value, $key, &$xml)
    {
        $xml->addChild($key, $value);
    }

    /**
     * @param DataObject $object
     * @param bool $useRecursion
     * @return array
     *
     * @todo Check for a specific "export" on a class in order to allow overriding the "default" way
     */
    private function exportObject(DataObject $object, $useRecursion=true)
    {
        $objectData = [];

        $className = 'Folder';

        if ($object->getType() !== 'folder') {

            /** @var DataObject\ClassDefinition $cl */
            $cl = $object->getClass();

            $className = $cl->getName();

            $fds = $cl->getFieldDefinitions();
            foreach ($fds as $fd) {
                $fieldName = $fd->getName();
                $fieldType = $fd->getFieldtype();

                $getterFunction = 'getForType' . ucfirst($fieldType);

                if (method_exists($this, $getterFunction)) {

                    $objectData[$fieldName] = $this->$getterFunction($object, $fieldName);
                } else {

                    $objectData[$fieldName] = ['_attributes' => ['skipped' => 'true', 'fieldtype'=>$fieldType]];

                    echo "Unsupported field type: " . $fieldType . ' in ' . $className . ' for '.$fieldName."\n";
                }
            }
        }



        $childDataList = [];

        if ($useRecursion) {
            $children = $object->getChildren();
            foreach ($children as $child) {
                $childData =  $this->exportObject($child);
                //var_dump($childData);exit;
                //$childDataList[] = [$childData['_attributes']['class'] => [$childData]];
                if (!array_key_exists($childData['_attributes']['class'], $childDataList)) {
                    $childDataList[$childData['_attributes']['class']] = [];
                }
                $childDataList[$childData['_attributes']['class']][] = $childData;
            }
        }

        $objectData['_attributes'] = [
            'id' => $object->getId(),
            'type' => $object->getType(),
            'key'  => $object->getKey(),
            'class' => $className,
        ];

        if ($childDataList !== []) {
            $objectData[':children'] = $childDataList;
        }

        return $objectData;
    }

    // add a getForType* method for every Pimcore Datatype:

    private function getForTypeManyToManyObjectRelation($object, $fieldname)
    {
        $relations = [];
        $getterFunction = 'get'.ucfirst($fieldname);
        foreach($object->$getterFunction() as $relationObject) {
            $exportObject = $this->exportObject($relationObject, false);
            $relations[] = [$exportObject['_attributes']['class']=>$exportObject];
        }

        return $relations;
    }

    private function getForTypeInput($object, $fieldname)
    {
        $getterFunction = 'get'.ucfirst($fieldname);
        $data = $object->$getterFunction();
        if ($data == null) {
            return $data;
        } else {
            return ['_cdata' => $data];
        }
    }

    private function getForTypeSelect($object, $fieldname)
    {
        return $this->getForTypeInput($object, $fieldname);
    }
}
