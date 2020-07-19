# Basilicom Xml Tool bundle for Pimcore

Adds commands for dealing with XML data. For now, parts
of the Object tree can be exported recursively, retaining
the hierarchy.   

## License

GPLv3 - see: gpl-3.0.txt

## Requirements

* Pimcore >= 6.0.0

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

For all options (writing to a file, etc.), see:

```
    bin/console basilicom:xmltool:export --help
```

### Limitations

Only a few field types are supported for now:

* input
* select
* ManyToManyObjectRelation

To extend the supported types, implement a
```getForType*``` method in ```ExportCommand.php```.


