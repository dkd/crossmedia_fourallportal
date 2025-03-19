[![Latest Stable Version](https://poser.pugx.org/crossmedia/fourallportal/v)](https://packagist.org/packages/crossmedia/fourallportal)
[![Total Downloads](https://poser.pugx.org/crossmedia/fourallportal/downloads)](https://packagist.org/packages/crossmedia/fourallportal)
[![Latest Unstable Version](https://poser.pugx.org/crossmedia/fourallportal/v/unstable)](https://packagist.org/packages/crossmedia/fourallportal)
[![PHP Version Require](https://poser.pugx.org/crossmedia/fourallportal/require/php)](https://packagist.org/packages/crossmedia/fourallportal)

PIM Connector
=============

Extension to facilitate import from PIM to TYPO3 records.

Requirements
-----------------------------------------

1. TYPO3 version 12.4 or higher
2. TYPO3 in composer mode
3. PHP version 8.1 or higher

Documentation and Guides
------------

1. [Install the 4ALLPORTAL Plugin](./Documentation/installation.md)
2. [Server Component](./Documentation/setup.md#server-component)
3. [Module Component](./Documentation/setup.md#module-component)
4. [Mapping Class](./Documentation/setup.md#mapping-class)
5. [Type Converter](./Documentation/setup.md#type-converter)
6. [Complex Types](./Documentation/setup.md#complex-types)
7. [Dynamic Domain Module](./Documentation/setup.md#dynamic-domain-module)
8. [Model Class File](./Documentation/setup.md#model-class-file)
9. [4ALLPORTAL Extension Commands](./Documentation/commands.md)

    - [Create session ID](./Documentation/commands.md#create-session)
    - [Generate TCA for model](./Documentation/commands.md#generate-tca-for-model)
    - [Generate abstract entity class](./Documentation/commands.md#generate-abstract-entity-class)
    - [Generate additional SQL schema file](./Documentation/commands.md#generate-additional-sql-schema-file)
    - [Generates all configuration](./Documentation/commands.md#generates-all-configuration)
    - [Get module and connector configuration](./Documentation/commands.md#get-module-and-connector-configuration)
    - [Initialize system](./Documentation/commands.md#initialize-system)
    - [Pin PIM schema version](./Documentation/commands.md#pin-pim-schema-version)
    - [Update models](./Documentation/commands.md#update-models)
    - [Sync data](./Documentation/commands.md#sync-data)
    - [Unlock sync](./Documentation/commands.md#unlock-sync)
    - [Replay events](./Documentation/commands.md#replay-events)
    - [Run tests](./Documentation/commands.md#run-tests)

10. [TCA Definitions File](./Documentation/setup.md#tca-definitions-file)
11. [Scheduled Tasks](./Documentation/setup.md#scheduled-tasks)
12. [Old Documentation (8.7)](./Documentation/oldDoccumentation.md)

Versions
-----------------------------------------

| Version | TYPO3     | PHP       | Support/Development                  |
|---------|-----------|-----------|--------------------------------------|
| 6.x     | 12.x      | 8.1 - 8.3 | Features, Bugfixes, Security Updates |
| 5.x     | 7.6 - 8.7 | 5.5 - 7.2 | Support dropped                      |

Migration from TYPO3 v8.x to v12.x
-----------------------------------------

Please refer to the [Migration Guide](./Documentation/migration.md) document included in this
repository.


Expected behavior
-----------------

Assuming that you put all of the above components together correctly, running the import
CLI command should cause the following chain of events to occur:

* Each `Server` is iterated
* Each `Module` from each `Server` is iterated
* The PIM API is queried using the credentials and configuration from `Server` and `Module`
* A list of events are received and spooled
* The events are claimed and processed one by one, performing one of either `update` or
  `delete` actions (note: `create` is compounded into `update` since ad-hoc creation happens)
* If successful, all properties received from PIM are mapped onto the Entity properties
  and saved to the database.
* If any errors should occur, feedback is output identifying the source of the problem.

Developer hints
---------------

The following hints may help developers avoid pitfalls when working with this logic:

1. Reflection is widely used and registration happens in extension config files, and both
   of these asset types are quite eagerly cached by TYPO3. The cache group that contains
   both of these caches is the `system` one which is normally hidden unless you are in
   `Development` context or the system cache flushing was explicitly allowed for your user.
2. Even though `TypeConverts` are used, Extbase's validation logic is not triggered. This
   means you can potentially save values in the DB that cause loading of the Entity to fail
   if for example it is passed as argument to a controller action (unless you disable
   validation for the argument in the controller action itself).
3. TYPO3 contains `TypeConverters` for standard types which may not be possible to override.
   Should you experience problems with this, it is possible to remove an already registered
   `TypeConverter` directly from the `TYPO3_CONF_VARS` array but this is strongly discouraged.
   If a given `TypeConverter` is unable to convert a value, consider wrapping said value
   in a ValueObject you attach to your Entity, then create a `TypeConverter` that converts
   to that type of ValueObject.
4. As far as humanly possible, try to adhere to the best practice described above and make
   your Entity as close to the PIM column structures as you can. Overriding any of the logic
   of the mapping classes or `TypeConverter` base class may cause vital features to stop
   working, e.g. could prevent proper handling of relations. The less you customise, the more
   likely it is that the default rules will handle your object types with no problems.
