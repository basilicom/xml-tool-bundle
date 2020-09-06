<?php

namespace Basilicom\XmlToolBundle\Service;

use Pimcore\Model\DataObject;
use Spatie\ArrayToXml\ArrayToXml;

class Xml
{
    /** @var XmlConfig */
    private $config;

    private $xslt = false;

    private $includeVariants = false;
    private $omitRelationObjectFields = false;

    private $language = null;

    /**
     * @param string|bool $xslt
     */
    public function setXslt($xslt): void
    {
        $this->xslt = $xslt;
    }

    /**
     * @return bool
     */
    public function isIncludeVariants(): bool
    {
        return $this->includeVariants;
    }

    /**
     * @param bool $includeVariants
     */
    public function setIncludeVariants(bool $includeVariants): void
    {
        $this->includeVariants = $includeVariants;
    }

    /**
     * @return bool
     */
    public function isOmitRelationObjectFields(): bool
    {
        return $this->omitRelationObjectFields;
    }

    /**
     * @param bool $omitRelationObjectFields
     */
    public function setOmitRelationObjectFields(bool $omitRelationObjectFields): void
    {
        $this->omitRelationObjectFields = $omitRelationObjectFields;
    }


    public function exportTree($object, $root)
    {

        $treeData = $this->exportObject($object);
        $treeData['_attributes']['xmlns:pc'] = 'https://basilicom.de/pimcore';

        $arrayToXml = new ArrayToXml($treeData, $root);

        $xmlDom = $arrayToXml->toDom();

        if ($this->xslt) {
            $xslDom = new \DOMDocument;
            $xslDom->load($this->xslt);

            $proc = new \XSLTProcessor;
            $proc->importStyleSheet($xslDom);

            $xmlDom = $proc->transformToDoc($xmlDom);
        }

        return $xmlDom;
    }

    /**
     * @param DataObject $object
     * @param bool $useRecursion
     * @return array
     *
     * @todo Check for a specific "export" on a class in order to allow overriding the "default" way
     */
    private function exportObject(DataObject $object, $useRecursion=true, $addFields=true)
    {
        $objectData = [];

        $className = 'Folder';

        if ($object->getType() !== 'folder') {

            /** @var DataObject\ClassDefinition $cl */
            $cl = $object->getClass();

            $className = $cl->getName();



            if ($addFields) {

                $fds = $cl->getFieldDefinitions();
                $this->processFieldDefinitions($fds, $object, $objectData);

            }

        }

        $childDataList = [];

        if ($useRecursion) {
            $children = $object->getChildren();
            foreach ($children as $child) {
                $childData =  $this->exportObject($child);
                if (!array_key_exists($childData['_attributes']['class'], $childDataList)) {
                    $childDataList[$childData['_attributes']['class']] = [];
                }
                $childDataList[$childData['_attributes']['class']][] = $childData;
            }
        }

        $variantDataList = [];

        if ($this->includeVariants) {
            $children = $object->getChildren([DataObject\AbstractObject::OBJECT_TYPE_VARIANT]);
            foreach ($children as $child) {
                $childData =  $this->exportObject($child);
                if (!array_key_exists($childData['_attributes']['class'], $variantDataList)) {
                    $variantDataList[$childData['_attributes']['class']] = [];
                }
                $variantDataList[$childData['_attributes']['class']][] = $childData;
            }
        }


        if ($childDataList !== []) {
            $objectData['pc:children'] = $childDataList;
        }

        if ($variantDataList !== []) {
            $objectData['pc:variants'] = $variantDataList;
        }

        $objectData['_attributes'] = [
            'id' => $object->getId(),
            'type' => $object->getType(),
            'key'  => $object->getKey(),
            'class' => $className,
            'is-variant-leaf' => (($object->getType()=='variant')&&(count($variantDataList)==0)?'true':'false'),
            'is-object-leaf' => (($object->getType()=='object')&&(count($childDataList)==0)?'true':'false'),
        ];

        return $objectData;
    }


    private function processFieldDefinitions($fds, $object, &$objectData)
    {

        foreach ($fds as $fd) {
            $fieldName = $fd->getName();
            $fieldType = $fd->getFieldtype();

            $getterFunction = 'getForType' . ucfirst($fieldType);

            if (method_exists($this, $getterFunction)) {

                $objectData[$fieldName] = $this->$getterFunction($object, $fieldName);

            } elseif ($fieldType == 'localizedfields') {

                $localizedFields = $fd->getFieldDefinitions();
                foreach (\Pimcore\Tool::getValidLanguages() as $language) {
                    $this->language = $language;
                    $this->processFieldDefinitions($localizedFields, $object, $objectData[$fieldName][$language]);
                }
                $this->language = null;
            } else {

                $objectData[$fieldName] = ['_attributes' => ['skipped' => 'true', 'fieldtype'=>$fieldType]];

                echo "Unsupported field type: " . $fieldType . ' for '.$fieldName."\n";
            }
        }

    }

    // add a getForType* method for every Pimcore Datatype:

    private function getForTypeManyToManyObjectRelation($object, $fieldname)
    {
        $relations = [];
        $getterFunction = 'get'.ucfirst($fieldname);
        foreach($object->$getterFunction() as $relationObject) {
            $exportObject = $this->exportObject($relationObject, false, !$this->omitRelationObjectFields);

            if (!array_key_exists($exportObject['_attributes']['class'], $relations)) {
                $relations[$exportObject['_attributes']['class']] = [];
            }
            $relations[$exportObject['_attributes']['class']][] = $exportObject;
        }

        return $relations;
    }

    private function getForTypeInput($object, $fieldname)
    {
        $getterFunction = 'get'.ucfirst($fieldname);

        if ($this->language == null) {

            $data = $object->$getterFunction();
            if ($data == null) {
                return $data;
            } else {
                return ['_cdata' => $data];
            }
        } else {
            $data = $object->$getterFunction($this->language);
            return ['_cdata' => $data, '_attributes' => ['language' => $this->language] ];
        }
    }

    private function getForTypeSelect($object, $fieldname)
    {
        return $this->getForTypeInput($object, $fieldname);
    }

    private function getForTypeNumeric($object, $fieldname)
    {
        $getterFunction = 'get'.ucfirst($fieldname);
        $data = $object->$getterFunction();
        return $data;
    }

    private function getForTypeTextarea($object, $fieldname)
    {
        return $this->getForTypeInput($object, $fieldname);
    }

    private function getForTypeWysiwyg($object, $fieldname)
    {
        return $this->getForTypeInput($object, $fieldname);
    }

    private function getForTypeDate($object, $fieldname)
    {
        $getterFunction = 'get'.ucfirst($fieldname);
        $data = (string)$object->$getterFunction();
        return $data;
    }

    private function getForTypeDatetime($object, $fieldname)
    {
        return $this->getForTypeDate($object, $fieldname);
    }

}
