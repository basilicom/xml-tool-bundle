<?php

namespace Basilicom\XmlToolBundle\Service;

use DOMDocument;
use DOMException;
use Pimcore\Model\DataObject;
use Spatie\ArrayToXml\ArrayToXml;
use XSLTProcessor;

class Xml
{
    /** @var XmlConfig */
    private $config;

    private $xslt = false;

    private $includeVariants = false;
    private $omitRelationObjectFields = false;

    private $language = null;

    private $exportCache = []; // stores id

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
     *
     * @return void
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
     *
     * @return void
     */
    public function setOmitRelationObjectFields(bool $omitRelationObjectFields): void
    {
        $this->omitRelationObjectFields = $omitRelationObjectFields;
    }

    /**
     * @param $object
     * @param $root
     *
     * @return DOMDocument|false
     *
     * @throws DOMException
     */
    public function exportTree($object, $root)
    {
        $treeData = $this->exportObject($object);
        $treeData['_attributes']['xmlns:pc'] = 'https://basilicom.de/pimcore';

        $arrayToXml = new ArrayToXml($treeData, $root);

        $xmlDom = $arrayToXml->toDom();

        if ($this->xslt) {
            $xslDom = new DOMDocument;
            $xslDom->load($this->xslt);

            $proc = new XSLTProcessor;
            $proc->importStyleSheet($xslDom);

            $xmlDom = $proc->transformToDoc($xmlDom);
        }

        return $xmlDom;
    }

    /**
     * @param DataObject $object
     * @param bool $useRecursion
     * @param bool $addFields
     *
     * @return array
     *
     * @todo Check for a specific "export" on a class in order to allow overriding the "default" way
     */
    private function exportObject(DataObject $object, $useRecursion=true, $addFields=true): array
    {
        $objectData = [];

        $this->exportCache[$object->getId()] = true; // remember that we are exported this object
        
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
            'is-variant-leaf' => (($object->getType()==='variant')&&(count($variantDataList)===0)?'true':'false'),
            'is-object-leaf' => (($object->getType()==='object')&&(count($childDataList)===0)?'true':'false'),
        ];

        return $objectData;
    }

    /**
     * @param $fds
     * @param $object
     * @param $objectData
     *
     * @return void
     */
    private function processFieldDefinitions($fds, $object, &$objectData): void
    {
        foreach ($fds as $fd) {
            $fieldName = $fd->getName();
            $fieldType = $fd->getFieldtype();

            $getterFunction = 'getForType' . ucfirst($fieldType);

            if (method_exists($this, $getterFunction)) {

                $objectData[$fieldName] = $this->$getterFunction($object, $fieldName);

            } elseif ($fieldType === 'localizedfields') {

                $localizedFields = $fd->getFieldDefinitions();
                foreach (\Pimcore\Tool::getValidLanguages() as $language) {
                    $this->language = $language;
                    $this->processFieldDefinitions($localizedFields, $object, $objectData[$fieldName][$language]);
                }
                $this->language = null;
            } else {

                $objectData[$fieldName] = ['_attributes' => ['skipped' => 'true', 'fieldtype'=>$fieldType]];

                //echo "Unsupported field type: " . $fieldType . ' for '.$fieldName."\n";
            }
        }
    }

    // add a getForType* method for every Pimcore Datatype:

    /**
     * @param $object
     * @param $fieldname
     *
     * @return array
     */
    private function getForTypeManyToManyObjectRelation($object, $fieldname): array
    {
        $relations = [];

        $getterFunction = 'get'.ucfirst($fieldname);
        /** @var array|null $relationObjects */
        $relationObjects = $object->$getterFunction();

        if (is_iterable($relationObjects)) {
            foreach($relationObjects as $relationObject) {
                
                $addFields = !$this->omitRelationObjectFields;

                if ($this->exportCache[$relationObject->getId()]) {
                    $addFields = false;
                }
                $exportObject = $this->exportObject($relationObject, false, $addFields);                

                if (!array_key_exists($exportObject['_attributes']['class'], $relations)) {
                    $relations[$exportObject['_attributes']['class']] = [];
                }
                $relations[$exportObject['_attributes']['class']][] = $exportObject;
            }
        }

        return $relations;
    }

    /**
     * @param $object
     * @param $fieldname
     *
     * @return array
     */
    private function getForTypeAdvancedManyToManyObjectRelation($object, $fieldname): array
    {
        $relations = [];
        $meta = [];

        $getterFunction = 'get'.ucfirst($fieldname);
        /** @var Data\ObjectMetadata[]|null $relationMetaObjects */
        $relationMetaObjects = $object->$getterFunction();

        if (is_iterable($relationMetaObjects)) {
            foreach ($object->$getterFunction() as $relationMetaObject) {
                $relationObject = $relationMetaObject->getObject();

                $addFields = !$this->omitRelationObjectFields;

                if ($this->exportCache[$relationObject->getId()]) {
                    $addFields = false;
                }
                $exportObject = $this->exportObject($relationObject, false, $addFields);                
                $data = $relationMetaObject->getData();

                $meta['pc:relation'][] = [
                    $exportObject['_attributes']['class'] => $exportObject,
                    'pc:meta' => $data
                ];
            }
        }

        return $meta;
    }

    /**
     * Alias - old field type!
     * @param $object
     * @param $fieldname
     *
     * @return array
     */
    private function getForTypeObjectsMetadata($object, $fieldname): array
    {
        return $this->getForTypeAdvancedManyToManyObjectRelation($object, $fieldname);
    }

    /**
     * Alias - old field type!
     * @param $object
     * @param $fieldname
     *
     * @return array
     */
    private function getForTypeObjects($object, $fieldname): array
    {
        return $this->getForTypeManyToManyObjectRelation($object, $fieldname);
    }

    /**
     * @param $object
     * @param $fieldname
     *
     * @return array|null
     */
    private function getForTypeInput($object, $fieldname): ?array
    {
        $getterFunction = 'get'.ucfirst($fieldname);

        if ($this->language === null) {

            $data = $object->$getterFunction();
            if ($data === null) {
                return $data;
            } else {
                return ['_cdata' => $data];
            }
        } else {
            $data = $object->$getterFunction($this->language);
            return ['_cdata' => $data, '_attributes' => ['language' => $this->language] ];
        }
    }

    /**
     * @param $object
     * @param $fieldname
     *
     * @return array|null
     */
    private function getForTypeImage($object, $fieldname): ?array
    {
        return $this->getForTypeInput($object, $fieldname);
    }

    /**
     * @param $object
     * @param $fieldname
     *
     * @return array|null
     */
    private function getForTypeSelect($object, $fieldname): ?array
    {
        return $this->getForTypeInput($object, $fieldname);
    }

    /**
     * @param $object
     * @param $fieldname
     *
     * @return mixed
     */
    private function getForTypeNumeric($object, $fieldname)
    {
        $getterFunction = 'get'.ucfirst($fieldname);
        return $object->$getterFunction();
    }

    /**
     * @param $object
     * @param $fieldname
     *
     * @return array|null
     */
    private function getForTypeTextarea($object, $fieldname): ?array
    {
        return $this->getForTypeInput($object, $fieldname);
    }

    /**
     * @param $object
     * @param $fieldname
     *
     * @return array|null
     */
    private function getForTypeWysiwyg($object, $fieldname): ?array
    {
        return $this->getForTypeInput($object, $fieldname);
    }

    /**
     * @param $object
     * @param $fieldname
     *
     * @return array|null
     */
    private function getForTypeRgbaColor($object, $fieldname): ?array
    {
        return $this->getForTypeInput($object, $fieldname);
    }

    /**
     * Alias, old field type!
     * @param $object
     * @param $fieldname
     *
     * @return array|null
     */
    private function getForTypeColor($object, $fieldname): ?array
    {
        return $this->getForTypeRgbaColor($object, $fieldname);
    }

    /**
     * @param $object
     * @param $fieldname
     *
     * @return string
     */
    private function getForTypeDate($object, $fieldname): string
    {
        $getterFunction = 'get'.ucfirst($fieldname);
        return (string)$object->$getterFunction();
    }

    /**
     * @param $object
     * @param $fieldname
     *
     * @return string
     */
    private function getForTypeDatetime($object, $fieldname): string
    {
        return $this->getForTypeDate($object, $fieldname);
    }
}
