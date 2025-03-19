# Setup Guide

## Server Component

In order to communicate with the remote API, a `Server` configuration is required. To create
a `Server` configuration you can use the `4AllPortal` backend module and click the plus icon.

The `Server` needs to be configured with the following information:

* A customer domain
* A customer name
* A username for login
* A password for login
* At least one `Module`

All of the above information will be delivered by the PIM service administrator.

## Module Component

When configuring a `Server` you must add one or more `Module` components. Each `Module` may
present different configuration fields depending on the type of the `Module` but all share a
few common fields:

* A connector name
* A mapping class selection box
* A storage folder selection box

Only the first of these values, the connector name, is provided by the PIM service admin. The
possible values are not documented here and depend entirely on the PIM service.

The remaining fields are used to configure how TYPO3 handles the connector.

Additional fields may be shown based on the selected mapping class.

## Mapping Class

In order to perform imports from a given connector, a mapping class is needed. This class
contains a few basic instructions for how to store the data (essentially, it knows the class
name of the Repository and therefore Entity it handles) as well as a handful of methods which
can be implemented if it is necessary to change the way the mapping happens by default.

A vanilla mapping class can look as simple as this:

```php
<?php
namespace Crossmedia\Products\Fourallportal;

use Crossmedia\Products\Domain\Repository\ProductRepository;
use Crossmedia\Fourallportal\Mapping\AbstractMapping;

class ProductMapping extends AbstractMapping
{
    /**
     * @var string
     */
    protected $repositoryClassName = ProductRepository::class;
}
```

Which is all that is needed in order to import data from the PIM service. Once the class is
created it must be registered:

```php
<?php
// ext_localconf.php of custom extension adding a mapper class
\Crossmedia\Fourallportal\Mapping\MappingRegister::registerMapping(
    \Crossmedia\Products\Fourallportal\ProductMapping::class
);
```

By default, a mapping class will attempt to map properties onto local Entity objects when
the names of columns in the PIM data set matches the property names of the Entity. For
example, a field named `title` in the PIM data will automatically be assigned to the `title`
property of the Entity handled by the mapping class.

**As far as possible, local columns and property names should match the PIM data columns
except where this conflicts with naming schemes**.

However, it isn't always possible to rely on a 1:1 mapping of columns - which is why the
mapping class registration method takes a second argument that is a column map:

```php
<?php
// Still in ext_localconf.php of custom extension
\Crossmedia\Fourallportal\Mapping\MappingRegister::registerMapping(
    \Crossmedia\Products\Fourallportal\ProductMapping::class,
    array(
        'emission_class' => 'emissionclass',
        'product_cat' => 'categories',
        'ignored_property' => false, // Mapping any incoming column name to FALSE ignores the column
    )
);
```

The second argument is an associative array where the keys are the PIM data column names
and the value is the target property name on the Entity.

Conversion of input data to proper TYPO3 data types is then done based on:
 1. the type of the PIM input value and 
 2. the expected type of the property on the Entity.

 #### Mapping class check() method

A special method exists on the MappingInterface which can be implemented to perform checks
on the mapping routines or objects specific to the mapping, in the backend module's server
inspection view.

A mapping class with a check method could be as simple as the following:

```php
<?php
namespace Crossmedia\Products\Fourallportal;

use Crossmedia\Products\Domain\Repository\ProductRepository;
use Crossmedia\Fourallportal\Mapping\AbstractMapping;
use Crossmedia\Fourallportal\Service\ApiClient;
use Crossmedia\Fourallportal\Domain\Model\Module;

class ProductMapping extends AbstractMapping
{
    /**
     * @var string
     */
    protected string $repositoryClassName = ProductRepository::class;
    
    public function check(ApiClient $client, Module $module, array $status)
    {
        // Perform a check that the Module is configured correctly to map this type.
        // The called method is an example, not an API method. You can do these checks
        // any way you prefer, as long as 
        if (!$this->myMethodToCheckModuleConfiguration($module)) {
            $status['class'] = 'danger'; // Overrides the CSS class for the Module info.
            $status['message'] .= '<p class="text-danger">The module was misconfigured!</p>';
            // Normally you'd provide more feedback - this is just an example. String
            // is simply appended to previous message(s), as HTML.
        }
        
        // Also, perform standard checks that the mapping configuration array is
        // consistent and targets existing properties which have setter methods.
        return parent::check($client, $module, $status);
        // Note: if you do not return parent::check() output, instead return $status
    }
}
```

Implement this method if your mapping class needs to do additional verification of things
like comparing the Module's configuration with API responses to detect misconfiguration.

For example, the FalMapping mapper checks if the shell path trimming and asset downloads
function correctly - and skips the check for mapping configuration because there is no true
model instance that can be analysed (FAL mapping happens through FAL, not Extbase models).


## Type Converter

All conversion of data types happens through the `TypeConverter` pattern in TYPO3. To know
how to convert a value, the mapping class looks at the Entity's property and attempts to
determine the type based on various Reflection analysis. It understands the following type
declaration strategies ranging from most to least trustworthy:

* Method signature of setter method demanding a specific type (including PHP7 strict types)
* If it exists, a `@param` annotation on the setter method is the second best option given
  that the setter will be used to set the value (thus potentially accepting a different type
  than the actual property it sets)
* If it exists, a `@var` annotation on the property of the Entity

If none of the above yields a result the import fails with an exception.

Once the desired target data type is determined, the mapping class uses a limited subset of
the features of the `PropertyMapper` to detect a `TypeConverter` that accepts the input data
type and yields the expected output type.


#### Edge case per-property conversion

It is not always the case that the standard Extbase/TYPO3 property types fit exactly with the
desired input- and output data types - and sometimes, not even a `TypeConverter` is flexible
enough to handle input values.

For these edge cases a special `ValueSetterInterface` is added which can be implemented by
a class and then configured in the mapping array:

 ```php
 <?php
 // In ext_localconf.php of custom extension
 \Crossmedia\Fourallportal\Mapping\MappingRegister::registerMapping(
     \Crossmedia\Products\Fourallportal\ProductMapping::class,
     array(
         'description' => \My\Special\ValueSetterImplementation::class,
     )
 );
 ```

```php
<?php
// The class used in that mapping array
namespace My\Special;

use Crossmedia\Fourallportal\Mapping\MappingInterface;
use Crossmedia\Fourallportal\Mapping\ValueSetterInterface;

class ValueSetterImplementation implements ValueSetterInterface
{
    public function setValueOnObject($value, $sourcePropertyName, array $inputData, $object, MappingInterface $mappingClass)
    {
        // Do something to the input property value that normal TypeConverters can't.
        // Or call special setters or other methods on the object to set values in ways
        // standard setters can't, for example when using non-standard setter methods
        // or methods which toggle multiple other properties based on data from the API.
        $object->doSomethingSpecialWithValue($value);
        // There is no return value since $object gets updated by reference. 
    }
}

```

Note that such properties are ignored when building dynamic properties. This means that if
the property you handle requires either a domain object property, TCA or SQL schema, then
you must manually define all of these things since they will not be added automatically.


#### The purpose of `TypeConverters`

The choice of `TypeConverters` to do the actual conversion is a way to approximate how TYPO3
itself would attempt to convert a value - and supporting any third party converters through
the TYPO3 API instead of inventing a new API solely for this purpose.

`TypeConverter`s are fairly simple to create in that they have one main method and two
properties that govern which types it accepts and yields. For convenience, a shared base
class for PIM-aware type conversion is added, making new converters excessively simple:

```php
<?php
namespace Crossmedia\Products\TypeConverter;

use Crossmedia\Products\Domain\Model\Product;

class ProductTypeConverter extends AbstractUuidAwareObjectTypeConverter implements PimBasedTypeConverterInterface
{
    /**
     * @var string
     */
    protected string $targetType = Product::class;
}
```

This `TypeConverter` only needs to declare which target type it supports - everything else
is handled by the base class.

The base class provides a few essential features:

* It supports loading a record by `remote_id` column as well as `uid`
* It will attempt to load first by `remote_id` then by `uid`
* If neither method is successful it creates a new object and sets the `remoteId` attribute

These behaviors when combined result in a very specific way of operation. It makes relations
between objects completely transparent - even if the input data refers to an object by its
remote ID, the correct object either gets resolved or created on-the-fly to ensure that no
invalid relations will ever be written.

All that is required for a relation to work, is annotating it correctly in the Entity class
and making sure it has the appropriate setter methods. Compound types are automatically
detected and processed, e.g. if the PIM data set contains an array of references to other
objects and the local Entity is correctly annotated, the mapping class knows to resolve each
of those references and yield an ObjectStorage that can be persisted by Extbase.

However, this choice has a few implications:

* One `TypeConverter` must be registered for every possible data type or Entity from PIm
* If no converter can be detected for a given type, the import fails.
* If another `TypeConverter` exists with a higher priority then it gets used. The default
  priority for any `TypeConverter` using the shared base class is a very high `90` for this
  very reason.

> Developer note: `null` is handled by bypassing all of the regular mapping. Deleting a
> property value is possible by passing `null` as the value in the PIM data set.

In order to register a `TypeConverter` you need a single line in `ext_localconf.php`:

```php
<?php
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerTypeConverter(
    \Crossmedia\Products\TypeConverter\ProductTypeConverter::class
);
```

## Complex Types

Certain properties from the remote API have a so-called complex type, which means the value
consists of the value itself, along with meta-information about the nature of the value.
For example, `CEMetric` types exist to cover length in millimeters and inches, weight in
kilos and pounds, and so on.

Usage of these types is automatic (and happens via a special TypeConverter, see above). You
can of course also map such ComplexType properties manually but the creation of the instances
themselves happens automatically and internally.

The way complex types work requires each possible complex type to be configured before it can
be used. An annotated example of such a configuration:

```php
<?php
// in ext_localconf.php of an extension
\Crossmedia\Fourallportal\DynamicModel\ComplexTypeFactory::createComplexTypeTemplate(
    'CEMetric', // The name this shared type has in the remote API
    'performance', // An internal name identifying what the value describes
    'hp', // A label that can be rendered after the value, such as "kg", "g", "mm" etc.
    'performance_imperial', // The specific property name (on any parent object type) which uses this complex type as value
    
    // The final parameter is a special matching array which contains a subset of the
    // field configuration array returned from the remote API. This array is then checked
    // when detecting a ComplexType to use for a certain field: if all values in this
    // array match the corresponding settings in the API's responses, then this complex
    // type will be used.
    [
        'type' => 'CEMetric',
        'name' => 'performance_imperial',
        'metric' => [
            'name' => 'performance',
            'defaultUnit' => 'hp'
        ]
    ]
);
```

When a complex type value is saved it gets saved as a 1:1 related record in the database.
This record contains information how to process the value (which type to cast) and is unique
for each property on each parent (parents never share complex type values: they are *not*
value objects as such!). Before saving it, the value coming from the remote API gets assigned
along with the data type the value had when it was received. The value getter method then
casts the value to the right type before returning it.


#### Accessing complex type values in templates and code

In order to "read" the value stored in a complex type, dotted path access can be used to
reference the value (or the label, or the name) of the type.

Example Fluid template accessing:

```xml
<!-- Assuming the variable {product} has a property "weight" that is a valid ComplexType -->

Product: {product.name}
Weight: <f:format.number decimals="1">{product.weight.normalizedValue}</f:format.number> {product.weight.label}

<!-- Which could for example output in the last line: "Weight: 5.0 kg" -->
```

Example PHP access:

```php
<?php
$weight = $product->getWeight();
$weightString = 'Weight: ' . number_format($weight->getNormalizedValue(), 2) . ' ' . $weight->getLabel();

// $weightString value for example: "Weight: 5.0 kg"
```

Alternatively, the special `TYPO3\CMS\Extbase\Reflection\ObjectAccess` class can be used to
extract a specific value by path (which is useful if the path comes from external sources):

```php
<?php
$weightAsDouble = ObjectAccess::getProperty($product, 'weight.normalizedValue');
```

Contrary to the chained PHP method calls, using ObjectAcceess throws an exception if the path
does not exist (which happens if the chain links can't be accessed with getter methods).

> Note: for exactly things like determining how many decimal places to render, the remote API
> actually returns this information, but stored in separate properties. So, for example, you
> may be calling another getter method first to get `$decimalPlaces` then use that when formatting
> the value you read from the complex type. Since these are stored separately there is unfortunately
> no way to return an already formatted value directly from the ComplexType instance.

## Dynamic Domain Module

The Fourallportal extension features dynamic models on the TYPO3 side, which basically means
that if you construct the model classes in a specific way, there is a command line command
you can execute to generate a base class using properties returned from the remote API.

Opting in to dynamic model properties requires the following steps:

1. The model class file must include a special call to load a dynamically generated base
   class before using it as base class for the model.
2. The TCA file for the model must create the `columns` array using `array_merge` to put
   together the basic properties and the output from a function call which reads the
   remote API's properties for the connector that handles the model class.

Note that the function call which generates the TCA should only be used in the array-returning
TCA definition file for the model's table - since it calls the remote API it would impact
performance negatively if the output was not allowed to be cached, which is the case if it
were used in a so-called `Overrides` TCA file (which directly modifies the TCA array instead
of returning an array).

The rest of the requirements are automatically handled:

1. SQL schema gets generated based on configured Modules and can be updated using the
   normal schema update approaches (install tool or via third party CLI commands).
2. The TCA gets generated automatically based on responses from the remote API.
3. Whenever possible, dynamic model classes are regenerated on-the-fly. If this cannot
   be done an exception is thrown informing the administrator to use the command line.

The system generates dynamic model classes in two steps: first, a safe fallback class is
generated to ensure that if runtime errors happen during custom properties processing,
there will be no fatal errors that classes cannot be found. Second step is to generate
the actual class with all the custom properties.

Properties which require custom types or special overrides can be handled in two different
ways depending on the desired outcome:

1. The model class property, getter and setter added in the dynamically generated parent class
   can be overridden (as long as the method signatures are compatible; the signatures on the
   parent class are intentionally as loose as possible)
2. Properties can be mapped to other properties, which for example makes it possible to create
   relations and values which don't come from the remote API.
   
The first strategy can be used when the property names are compatible and for example the getter
method should transform the value before returning it. The second strategy can be used when
field names are incompatible or when the field is using a data type that doesn't come from the
remote API (for example, a relation to a TYPO3-only record like a domain, a user, a page, etc.). 

The following sections describe how to integrate the three main requirements


## Model Class File

The model file looks like any other domain model class you find in TYPO3 with one exception:
before the class is defined, a special API is used to load the parent class. This class is then
loaded separately from the normal class loading (since the class file exists as a pseudo cache
file containing a class definition).

If the parent class for some reason cannot be loaded a specific error message is used instead
of just reporting a missing class.

In other words: the dynamic parent class is used instead of `AbstractEntity` but in all other
ways the model works like a normal Extbase domain model. 

An annotated domain model class example:

```php
<?php
// Load the class, and generate the fallback and attempt to generate the dynamic
// class file if this is possible (config complete, API responding) and if not already
// generated. Any inclusion generates all classes for all modules since relations may
// point to other models.
DynamicModelGenerator::loadAbstractClass(AbstractProduct::class);

/**
 * Product
 *
 * The implementation using the dynamic parent class.
 * This class can contain custom properties or overrides
 * of properties on the parent class.
 */
class Product extends AbstractProduct
{
    /**
     * Example of a property which needs a special instance
     * type. This property is overridden here and annotated
     * with a different property type than the one coming
     * from the remote API. This annotation is then read and
     * assuming you have a so-called "TypeConverter" that can
     * convert from the data type the remote API returns, to
     * this class type, the value from the remote API gets
     * converted to an instance of `My\Special\Class` before
     * it is passed to the setter method.
     *
     * @var \My\Special\Class
     */
    protected $specialProperty;
    
    // Imagine a standard getter and setter method for $this->specialProperty here

    /**
     * Overridden getter for a dynamically added property.
     * Useful if the value must be transformed before display,
     * for example to preserve only some tags in HTML that
     * you display in the template without escaping it.
     *
     * The property name in the remote API in this case is
     * "my_property" which in TYPO3 gets converted to the
     * lowerCamelCase format and becomes "myProperty".
     *
     * @return string
     */
    public function getMyProperty()
    {
        return strip_tags($this->myProperty, '<a><em><i><strong><b>');
    }
}
```

Note: in order to make these classes visible to an IDE, the file system path must be included
and not excluded. The default folder in which classes get generated is:

```
./var/cache/code/fourallportal_classes/
```

Normal practice is to exclude scanning files in `./var` but in the context of fourallportal
it is very helpful to not exclude this specific folder.

You can also inspect the generated classes in this folder. They use a shared-hash filename with
a suffix to identify the fallback version.


#### Preventing use of the automatic model

In some cases it may be necessary to opt out from dynamic model generation. There are two ways
in which this can be done, both of which are equally valid:

1. You can manually create the `AbstractXyz` class in the namespace where it is expected. When
   the class already exists there, attempting to load the class returns that implementation
   instead of the dynamic one _even if you called the special loadClass API method in a model
   class file!_
2. You can configure a toggle in the Module settings in TYPO3 to disable the dynamic model
   feature on a per-connector/module basis. Doing this implies you *must* manually create the
   parent class and simply not use the parent class nor API related when creating your model
   class and TCA for the entity.

## TCA definitions file

TCA (Table Configuration Array) must be written for entities - just like any other Extbase
model requires it - but the TCA array must be constructed with some parts using an API call
to the fourallportal extension, to add the dynamic columns that are returned from the remote
API. Without this TCA, the properties will simply be ignored when they are persited; the
query to update the database will not contain the columns at all.

The following is a *heavily* truncated example TCA configuration file, like the ones you would
place in your extension to configure your entities:

```php
<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:products/Resources/Private/Language/locallang_db.xlf:tx_products_domain_model_product',
    ],
    'columns' => array_merge(
        \Crossmedia\Fourallportal\DynamicModel\DynamicModelGenerator::generateTableConfigurationForModuleIdentifiedByModelClassName(\Crossmedia\Products\Domain\Model\Product::class),
        [
            'brand' => [
                'exclude' => true,
                'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_db.xlf:tx_products_domain_model_product.brand',
                'config' => [
                    'type' => 'input',
                ],
            ],
        ]
    )
];
```

The important part to notice is that instead of hard-coding an array in the `columns` index,
as is normally done with TCA, the PHP function `array_merge()` is called with two arrays as
input to create a merged result:

1. The dynamic array of TCA based on columns returned from the remote API. 
2. The associative array you would normally define in `columns` which describe all of the
   properties that you *manually* added or are required by TYPO3 itself. This array gets quite
   big and in this example only contains a single field; normally you have 10+.

Anything you define in the second array will overrule what is generated in the first array. If
you need this to be different simply pass the API call as second array for `array_merge`.

The TCA generated this way gets cached by TYPO3 and will not be cleared unless the "all" or
"system" caches are cleared, which fits perfectly with the rebuilding of the model class files.


#### Completely automated models

If your models are essentially duplicates of each other with different names, which often is
the case when using the dynamic model feature, you may wish to have all of the schema sources
generated for you using standard composition.

For models which have completely boilerplate TCA and SQL schemas you can use this API:


```php
<?php
\Crossmedia\Fourallportal\DynamicModel\DynamicModelRegister::registerModelForAutomaticHandling(\My\Ext\Domain\Model\MyModel::class);
```

Combined with the following TCA file (which should be placed in the standard TCA file path):


```php
<?php
return \Crossmedia\Fourallportal\DynamicModel\DynamicModelGenerator::generateAutomaticTableConfigurationForModelClassName(
    \My\Ext\Domain\Model\MyModel::class
);
```

Doing so causes the following to happen:

1. You no longer have to define the entity's table in `ext_tables.sql` as the schema will be
   generated completely automatically.
2. You need no other TCA, but can still override the properties by the standard TCA overrides
   file path convention.

Note that this strategy can be combined with custom domain model properties. When doing this
you must add the custom fields only, in `ext_tables.sql` (e.g. a partial schema like when
adding fields on `pages` or `tt_content`), and must add the TCA for that field using TCA
override files - and must manually add the fields to the list of shown fields. It is highly
recommended to use the TYPO3 API for adding TCA fields for this use case!



#### The model update command

There are two ways to force dynamic models to be updated.

From the command line (or as scheduler task executing the "Extbase Controller Command")

```bash
./bin/typo3 fourallportal:updateModels
```

This command force-updates all models by reading the remote API and then regenerating
the classes (overwriting already generated ones if they exist).

The second way is to flush the TYPO3 system caches. This also triggers a model rebuild
and is intended as a way to let developers quickly trigger the rebuild on demand. Note
that the rebuild only happens if you flush the "all" or "system" caches.

##### Dimension Handling

This extension supports the import of Fields that contain differents dimension values.
To map Dimension Values to specific TYPO3 Languages you need to configure Mappings in the 
Backend of TYPO3 in the Server Configuration. For each Language you want to map a Dimension
to you need to create an Inline DimensionMapping Record that specifies the Language this
Dimension should be mapped to and the Name/Value pairs to identify a specific Dimension.

## Scheduled Tasks


In order to schedule the import command so it will execute at regular intervals, you have
two main strategies available to you:

1. Directly running the import command from crontab
2. Creating a scheduled task in TYPO3 which executes the command controller

The first option is covered in extensive detail in `man crontab` on any UNIX system so it
won't be covered here.

The second option, adding a scheduled task in TYPO3, requires the following steps:

* Make sure a crontab task exists which calls `./bin/typo3 scheduler:run` at
  regular intervals. The interval you set for this crontab task will be the lowest possible
  frequency at which jobs can run in TYPO3; suggested value is every 5 minutes.
* Make sure the `scheduler` system extension is installed in TYPO3.
* In the Scheduler backend module, add a new task and select the "Extbase command controller"
  task from the list of task types.
* Fill in an interval, for example `*/5 * * * *` or `300` which both mean every 5 minutes.
* Now save the scheduled task (save, do not save and close).
* Scroll down to the newly added field where you select the Extbase command controller to be
  executed. Select the `Fourallportal fourallportal: sync` command type.
* Now save the task again (save, do not save and close).
* Once again, scroll down and you will find the last configurable field with the label
  `sync`. If you enable this field, the task will do a full synchronisation resetting the
  last received event ID and spooling all events again. Leaving it disabled makes the task
  start with the last received event.
  
The necessity to save and re-edit the task multiple times is caused by the slightly old API
that exists in Scheduler: it does not allow scanning all of the available tasks and their
arguments, so the command options do not become available until a task type is selected and
the task is saved - and the arguments the command takes does not become available until the
command type is selected and the task is saved again.

This approach is necessary with any Extbase command controller.

Note that the `sync` argument should only be enabled if you explicitly want the task to do
a full synchronisation every time it runs. This should never be enabled on production
systems; it is there mostly for development and resetting if/when event errors should occur. 

Like the `sync` command, the `updateModels` command can also be scheduled should you wish to
make your models be updated at certain times. The recommendation however is to only trigger
model rebuilding manually since updates may reveal a need to extend the local mapping info
or add properties to models.


