# Basilicom Xml Tool bundle for Pimcore

Adds commands for dealing with XML data. For now, parts
of the Object tree can be exported recursively, retaining
the hierarchy.   

## License

GPLv3 - see: gpl-3.0.txt

## Requirements

* Pimcore >= 6.0.0
* XSL PHP extension for --xslt option support

## Installation

1) Install the bundle using composer `composer require basilicom/xml-tool-bundle dev-master`.
2) Execute `bin/console pimcore:bundle:enable BasilicomXmlToolBundle`.


## Configuration

n/a

### Usage

Use the export command to export command to export
the Object tree, example for path ```/foo```: 

```
    bin/console basilicom:xmltool:export /foo
```

Example output:

Note: The Object tree ```/``` contains in the example a single Object of 
the Object Class ```Bar``` with a single Input property of ```name```.

```
Exporting tree of Objects starting at /
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

### Limitations

Only a few field types are supported for now:

* input
* select
* wysiwyg
* textarea
* date
* datetime
* ManyToManyObjectRelation

To extend the supported types, implement a
```getForType*``` method in ```ExportCommand.php```.


