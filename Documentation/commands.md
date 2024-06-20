# 4ALLPORTAL Extension Commands

In order to get all the extension commands, run `typo3 list fourallportal`.

## Create Session

Logs in on the specified server (or active server) and outputs the session ID, which can then be
used for testing in for example raw CURL requests.

**Usage:** `fourallportal:createSession [<server>]`

| Argument | Description            |
|----------|------------------------|
| `server` | server id [default: 0] |

## Generate abstract entity class

Generate abstract entity class

This command can be used as substitute for the automatic
model class generation feature. Each entity class generated
with this command prevents usage of the dynamically created
class (which still gets created!). To re-enable dynamic
operation simply remove the generated abstract class again.

Generates an abstract PHP class in the same namespace as
the input entity class name. The abstract class contains
all the dynamically generated properties associated with
the Module.

**Usage:** `fourallportal:generateAbstractModelClass <entityClassName> [<strict>]`

| Argument          | Description                                         |
|-------------------|-----------------------------------------------------|
| `entityClassName` | entityClassName                                     |
| `strict`          | If TRUE, generates strict PHP code [default: false] |

## Generate TCA for model

This command can be used instead or together with the
dynamic model feature to generate a TCA file for a particular
entity, by its class name.

Internally the class name is analysed to determine the
extension it belongs to, and makes an assumption about the
table name. The command then writes the generated TCA to the
exact TCA configuration file (by filename convention) and
will overwrite any existing TCA in that file.

Should you need to adapt individual properties such as the
field used for label, the icon path etc. please use the
Configuration/TCA/Overrides/$tableName.php file instead.

**Usage:** `fourallportal:generateTableConfiguration <entityClassName> [<readOnly>]`

| Argument          | Description                                                 |
|-------------------|-------------------------------------------------------------|
| `entityClassName` | entityClassName                                             |
| `readOnly`        | If TRUE, generates TCA fields as read-only [default: false] |

## Generate additional SQL schema file

Generate additional SQL schema file

This command can be used as substitute for the automatic
SQL schema generation - using it disables the analysis of
the Module to read schema properties. If used, should be
combined with both of the other "generate" commands from
this package, to create a completely static set of assets
based on the configured Modules and prevent dynamic changes.

Generates all schemas for all modules, and generates a static
SQL schema file in the extension to which the entity belongs.
The SQL schema registration hook then circumvents the normal
schema fetching and uses the static schema instead, when the
extension has a static schema.

**Usage:** `fourallportal:generateSqlSchema`

## Generates all configuration

Generates all configuration

Shortcut method for calling all of the three specific
generate commands to generate static configuration files for
all dynamic-model-enabled modules' entities.

**Usage:** `fourallportal:generate <entityClassName> [<strict> [<readOnly>]]`

| Argument          | Description                                                 |
|-------------------|-------------------------------------------------------------|
| `entityClassName` | entityClassName                                             |
| `strict`          | If TRUE, generates strict PHP code [default: false]         |
| `readOnly`        | If TRUE, generates TCA fields as read-only [default: false] |

## Get module and connector configuration

Gets the module and connector configuration for the module identified by $moduleName, and outputs it
as JSON.

**Usage:** `fourallportal:getConfiguration <moduleName> [<server>]`

| Argument     | Description                                                    |
|--------------|----------------------------------------------------------------|
| `moduleName` | Name of module for which to get configuration                  |
| `server`     | Optional UID of server, defaults to active server [default: 0] |

## Initialize system

Creates Server and Module configuration if configured in
extension configuration. The array in:

`$GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['fourallportal']`

can contain an array of servers and modules, e.g.:

```PHP
[
  'default' => [
    'domain' => '',
    'customerName' => '',
    'username' => '',
    'password' => '',
    'active' => 1,
    'modules' => [
      'module_name' => [
        'connectorName' => '',
        'mappingClass' => '',
        'shellPath' => '',
        'falStorage' => '',
        'storagePid' => '',
      ],
    ],
  ],
]
```

Note that the module properties may differ depending on which
mapping class the module uses, and that the server name does
not get used - it is only there to identify the entry in your
configuration file

**Usage:** `fourallportal:initialize [<fail>]`

| Argument | Description                                                                                         |
|----------|-----------------------------------------------------------------------------------------------------|
| `fail`   | If TRUE, any connectivity test failure will cause the command to exit with failure [default: false] |

## Pin PIM schema version

Pins the PIM schema version, updating all local modules to use the
version of configuration that is currently live on the configured
remote server.

Used when a schema version mismatch prevents PIM sync from running.

**Usage:** `fourallportal:pinschema`

## Update models

Updates local model classes with properties as specified by
the mapping information and model information from the API.
Uses the Server and Module configurations in the system and
consults the Mapping class to identify each model that must
be updated, then uses the DynamicModelHandler to generate
an abstract model class to use with each specific model.

A special class loading function must be used in the model
before it can use the dynamically generated base class. See
the provided README.md file for more information about this.

**Usage:** ` fourallportal:updateModels`

## Sync data

Execute this to synchronise events from the PIM API

**Usage:** `fourallportal:sync [<sync> [<fullSync> [<module> [<exclude> [<force> [<execute> [<maxEvents> [<maxTime> [<maxThreads>]]]]]]]]]`

| Argument     | Description                                                                                                                                                      |
|--------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `sync`       | Set to true to sync events (starting from last received event). If execute=true will happen before executing [default: false]                                    |
| `fullSync`   | Set to true to trigger a full sync [default: false]                                                                                                              |
| `module`     | If passed can be used to only sync one module, using the module or connector name it has in 4AP                                                                  |
| `exclude`    | Exclude a list of modules from processing (CSV string module names)                                                                                              |
| `force`      | If set, forces the sync to run regardless of lock and will neither lock nor unlock the task [default: false]                                                     |
| `execute`    | If true, also executes events after receiving (syncing) events [default: false]                                                                                  |
| `maxEvents`  | Maximum number of events to process. Default is unlimited. Affects only the number of events being executed, if sync is enabled will still sync all [default: 0] |
| `maxTime`    | Maximum number of seconds that the sync is allowed to run, once expired, will require a new execution to continue [default: 0]                                   |
| `maxThreads` | Maximum number of concurrent threads which are allowed to execute events. Ignored if sync=true [default: 4]                                                      |

## Unlock sync

Removes a (stale) lock.

**Usage:** `fourallportal:unlock [<requiredAge>]`

| Argument      | Description                                                                                          |
|---------------|------------------------------------------------------------------------------------------------------|
| `requiredAge` | Number of seconds, required minimum age of the lock file before removal will be allowed [default: 0] |

## Replay events

Replays the specified number of events, optionally only
for the provided module named by connector or module name.

By default, the command replays only the last event.

**Usage:** `fourallportal:replay <module> [<events> [<objectId>]]`

| Argument   | Description           |
|------------|-----------------------|
| `module`   | Module name           |
| `events`   | Event id [default: 1] |
| `objectId` | Object Id             |

## Run tests

Runs tests on schema and response consistency and performs tracking
of basic response changes, i.e. simple diffs of which properties
are included in the response.

Outputs streaming YAML.

**Usage:** `fourallportal:test [<onlyFailed> [<withHistory>]]`

| Argument      | Description                                                                                         |
|---------------|-----------------------------------------------------------------------------------------------------|
| `onlyFailed`  | If TRUE, only outputs failed properties [default: false]                                            |
| `withHistory` | If TRUE, includes a tracking history of schema/response consistency for each module [default: true] |
