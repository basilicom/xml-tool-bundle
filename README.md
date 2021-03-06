# Basilicom Xml Tool bundle for Pimcore

Adds commands for dealing with XML data. For now, parts
of the Object tree can be exported recursively, retaining
the hierarchy.   

Exports can be triggered by a console command and written
to stdout, a file or an pimcore asset.

If enabled, exports can be made available via a REST API, too.

Table of contents
=================

<!--ts-->
   * [Basilicom Xml Tool bundle for Pimcore](#basilicom-xml-tool-bundle-for-pimcore)
      * [License](#license)
      * [Requirements](#requirements)
      * [Installation](#installation)
      * [Configuration](#configuration)
         * [Console Usage](#console-usage)
         * [Usage: REST API](#usage-rest-api)
         * [Limitations](#limitations)
<!--te-->

## License

GPLv3 - see: LICENSE

## Requirements

* PHP >= 7.1
* Pimcore >= 5.0.0
* XSL PHP extension for --xslt option support

## Installation

1) Install the bundle using composer `composer require basilicom/xml-tool-bundle dev-master`.
2) Execute `bin/console pimcore:bundle:enable BasilicomXmlToolBundle`

## Configuration

n/a

### Console Usage

Use the export command to export command to export
the Object tree, example for path ```/foo```: 

```
    bin/console basilicom:xmltool:export /foo
```

Example output:

Note: The Object tree ```/foo``` contains in the example a single Object of 
the Object Class ```Bar``` with a single Input property of ```name```.

```
Exporting tree of Objects starting at /foo
<?xml version="1.0"?>
<root id="1" type="folder" key="" class="Folder">
  <:children>
    <Bar id="4" type="object" key="baaaar" class="Bar">
      <name><![CDATA[bar]]></name>
    </Bar>
  </:children>
</root>
```

Complex example:

* do not export the attributes / fields of objects attached via relations
* export variants of objects, too
* change the name of the root node to 'Products'
* apply a sample xslt
* export to a pimcore asset ```/output/my-export.xml```

```
./bin/console basilicom:xmltool:export --omit-relation-object-fields --include-variants --root=products --asset=/output/my-export.xml --xslt sample.xsl /exp
```

Sample XSLT:

```xml
<?xml version="1.0"?>
<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:template match="/">
    <objects>
    <xsl:for-each select="//Leaf">
      <object>
        <name><xsl:value-of select="name"/></name>
      </object>
    </xsl:for-each>
    </objects>
  </xsl:template>
</xsl:stylesheet>
```

For all options (writing to a file, etc.), see:

```
    bin/console basilicom:xmltool:export --help
```

### Usage: REST API

In order to enable the REST API, place a configuration file in ```app/config/local/xmltool.yml```:

```yaml
basilicom_xml_tool:
  api:
    enabled: true
    endpoints:
      test1:
        token: secrettoken0815
        root: Products # Root name of the exported XML file
        path: /export/products # Path to the exporting objects
        xslt: ../sample.xsl
        include_variants: true
        omit_relation_object_fields: true
      test2:
        path: /sample/obj/path
```

This example enables two endpoint URLs:

* https://PIMCORE-SERVER/xml-tool/export/test1?token=secrettoken0815
* https://PIMCORE-SERVER/xml-tool/export/test2

### Limitations

Only a few field types are supported for now:

* input
* select
* wysiwyg
* textarea
* date
* datetime
* ManyToManyObjectRelation
* color
* rgbaColor
* localizedFields

To extend the supported types, implement a
```getForType*``` method in ```Service/Xml.php```.


