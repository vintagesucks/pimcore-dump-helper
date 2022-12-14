# pimcore-dump-helper
[![Build Status](https://github.com/vintagesucks/pimcore-dump-helper/workflows/Build/badge.svg)](https://github.com/vintagesucks/pimcore-dump-helper/actions) [![Dependabot](https://badgen.net/badge/Dependabot/enabled/green?icon=dependabot)](https://dependabot.com/) [![Latest Version](https://img.shields.io/packagist/v/vintagesucks/pimcore-dump-helper)](https://packagist.org/packages/vintagesucks/pimcore-dump-helper) 

Pimcore database dump helper with focus on dump readability.

## Installation

You can install the package via Composer:

```bash
composer require vintagesucks/pimcore-dump-helper --dev
```

## Configuration

You have to configure `PIMCORE_DB_DSN` in your environment:

```sh
PIMCORE_DB_DSN="mysql://user:password@127.0.0.1:3306/database"
```

Optionally you can set `DUMP_NO_DATA` to skip data for some tables:

```sh
DUMP_NO_DATA="cache_items,edit_lock,tmp_store"
```

## Usage

```bash
vendor/bin/pimcore-dump-helper database:dump
```
