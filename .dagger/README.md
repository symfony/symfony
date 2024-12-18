# Dagger PHP SDK

## Initializing the dagger runtime on this project

You'll need to do this before you can run the dagger command

``` bash
dagger init --sdk=php .
```

## Running the phpstan tests

### Running phpstan on the entire repository

``` bash
dagger call phpstan --source=.
```

### Running phpstan on all components

``` bash
dagger call phpstan-components --source=.
```

### Running phpstan on a specific component

``` bash
dagger call phpstan-component --source=. --component=Asset
```


## Running psalm tests


### Running psalm on the entire repository

``` bash
dagger call psalm --source=.
```

### Running psalm on all components

``` bash
dagger call psalm-components --source=.
```

### Running psalm on a specific component

``` bash
dagger call psalm-component --source=. --component=Asset
```

