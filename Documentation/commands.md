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

**Usage:** `fourallportal:generateAbstractModelClass [options] <entityClassName>`

| Argument          | Description                                                         |
|-------------------|---------------------------------------------------------------------|
| `entityClassName` | Name of the entity class. Use two back slashes on the command line  |

| Option     | Description                                |
|------------|--------------------------------------------|
| `--strict` | Generates strict PHP code |

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

**Usage:** `fourallportal:generateTableConfiguration [options] <entityClassName>`

| Argument          | Description                                                 |
|-------------------|-------------------------------------------------------------|
| `entityClassName` | entityClassName                                             |

| Option     | Description                                        |
|------------|----------------------------------------------------|
| `--read-only`     | Generates TCA fields as read-only |

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

**Usage:** `fourallportal:generate [options] <entityClassName>`

| Argument          | Description                                                 |
|-------------------|-------------------------------------------------------------|
| `entityClassName` | entityClassName                                             |

| Option         | Description                       |
|----------------|-----------------------------------|
| `--strict`     | Generates strict PHP code         |
| `--read-only`  | Generates TCA fields as read-only |

## Get module and connector configuration

Gets the module and connector configuration for the module identified by $moduleName, and outputs it
as JSON.

**Usage:** `fourallportal:getConfiguration [options] [--] <moduleName>`

| Argument     | Description                                                    |
|--------------|----------------------------------------------------------------|
| `moduleName` | Name of module for which to get configuration                  |

| Options     | Description                                        | Default |
|-------------|----------------------------------------------------|---------|
| `--server`  | Optional UID of server, defaults to active server  | 0       |

## Initialize system

Creates Server and Module configuration if configured in
extension configuration. The array in:

`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['fourallportal']`

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

**Usage:** `fourallportal:initialize [options]`

| Options  | Description                                                                               |
|----------|-------------------------------------------------------------------------------------------|
| `--fail` | Any connectivity test failure will cause the command to exit with failure |

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

**Usage:** `fourallportal:sync [options] [--] [<module>]`

| Argument     | Description                                                                                                                                                      |
|--------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `module`     | If passed can be used to only sync one module, using the module or connector name it has in 4AP                                                                  |


| Options         | Description                                                                                                                                         | Default |
|-----------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|---------|
| `--sync`        | Sync events (starting from last received event). If execute=true will happen before executing                                                       |         |
| `--full-sync`   | Trigger a full sync                                                                                                                                 |         |
| `--force`       | Forces the sync to run regardless of lock and will neither lock nor unlock the task                                                                 |         |
| `--execute`     | If true, also executes events after receiving (syncing) events                                                                                      |         |
| `--exclude`     | Exclude a list of modules from processing (CSV string module names)                                                                                 |         |
| `--max-events`  | Maximum number of events to process. Default is unlimited. Affects only the number of events being executed, if sync is enabled will still sync all | 0       |
| `--max-time`    | Maximum number of seconds that the sync is allowed to run, once expired, will require a new execution to continue                                   | 0       |
| `--max-threads` | Maximum number of concurrent threads which are allowed to execute events. Ignored if sync=true                                                      | 4       |

## Unlock sync

Removes a (stale) lock.

**Usage:** `fourallportal:unlock [options]`

| Options          | Description                                                                               | Default |
|------------------|-------------------------------------------------------------------------------------------|---------|
| `--required-age` | Number of seconds, required minimum age of the lock file before removal will be allowed   | 0       |

## Replay events

Replays the specified number of events, optionally only
for the provided module named by connector or module name.

By default, the command replays only the last event.

**Usage:** `fourallportal:replay [options] [--] <module>`

| Argument   | Description           |
|------------|-----------------------|
| `module`   | Module name           |

| Options       | Description                      | Default |
|---------------|----------------------------------|---------|
| `--events`    | The amount of events to process  | 1       |
| `--object-id` | The id of the object to replay   |         |

## Run tests

Runs tests on schema and response consistency and performs tracking
of basic response changes, i.e. simple diffs of which properties
are included in the response.

Outputs streaming YAML.

**Usage:** `fourallportal:test [options]`

| Options          | Description                                                                                |
|------------------|--------------------------------------------------------------------------------------------|
| `--only-failed`  | Only outputs failed properties                                                             |
| `--with-history` | Includes a tracking history of schema/response consistency for each module |
