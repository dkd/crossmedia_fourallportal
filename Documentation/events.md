# Events

## Synopsis

This part of the documentation describes the available events that the extension offers.

## Model generation

All events in this section related to the dynamic model, TCA and SQL generation.

### \Crossmedia\Fourallportal\Event\Tca\FieldSettingsEvent

#### What it's for

This event allows to manipulate the TCA settings for a specific field.

This events triggers after the TCA configuration for a field is ready and before it is added to the table configuration.

#### When to use it

Use this event in case you want to do minimal changes to a field configuration like changing the render type.

#### Important note

Changing the field type will not change the corresponding SQL structure!

For example changing from input to number will not change the SQL from varchar to int.

#### Public properties

| Property         | Type   | Readonly | Description                           |
|------------------|--------|----------|--------------------------------------|
| pimFieldType     | string | yes      | The pim field type                   |
| tableName        | string | yes      | The table name the column is located |
| fieldName        | string | yes      | The field name the TCA relates to    |

#### Public methods

| Method      | Argument     | Return type | Description                         |
|-------------|--------------|-------------|------------------------------------|
| `getTca()`  | N/A          | array       | Returns the field configuration    |
| `setTca()`  | array $tca   | void        | Sets the field field configuration |

### \Crossmedia\Fourallportal\Event\Tca\LabelFieldEvent

#### What it's for

This event allows you to change the value of the field `label` inside the controls of the TCA table definition.

#### When to use it

Use this event in case you want to specify a different label field.

Since the field calculation currently based on the type of the TCA,
changing the label can be a good choice to improve the use within the TYPO3 backend.

#### Public properties

| Property         | Type   | Readonly | Description                                  |
|------------------|--------|----------|---------------------------------------------|
| tableName        | string | yes      | The table name, the label is calculated for |
| fieldName        | string | yes      | The current calculated label field          |
| availableColumns | array  | yes      | Available column names                      |

#### Public methods

| Method         | Argument           | Return type | Description                                                                          |
|----------------|--------------------|-------------|-------------------------------------------------------------------------------------|
| getLabelField  | N/A                | string      | Returns the label field. This can be different from the value of property fieldName |
| setLabelField  | string $labelField | void        | Sets a new column name to used as label                                             |
