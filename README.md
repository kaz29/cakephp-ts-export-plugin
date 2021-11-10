# TsExport plugin for CakePHP

A command to output the typescript interface for CakePHP entities.

## Installation

You can install this plugin into your CakePHP application using [composer](https://getcomposer.org).

The recommended way to install composer packages is:

```
composer require --dev kaz29/ts-export
```

## Usage

```
bin/cake export_entity --all
```

## Example

```
bin/cake export_entity  Users
/**
 * User entity interface
 */
export interface User {
  id: number
  name: string
  email: string
  password: string
  created?: string
  modified?: string
}
```

## Author

Kazuhiro Watanabe - cyo [at] mac.com - [https://twitter.com/kaz_29](https://twitter.com/kaz_29)

## License

TsExport plugin for CakePHP is licensed under the MIT License - see the LICENSE file for details
