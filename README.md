# Query Builder

These builders are independent of any particular database connection library.

## SelectBuilder

```php
use ziguss\QueryBuilder\SelectBuilder;

list($sql, $params) = (new SelectBuilder('mysql'))
    ->select('c1, c2 as 2c')
    ->addSelect(['c3', 'c4'])
    ->selectRaw('1 + 1')
    ->from('table1 t1')
    ->innerJoin(['t2' => 'table2'], 't1.id = t2.t1_id')
    ->where([
        'c1' => 1,
        'c2' => ['v1', 'v2'],
        'c4' => null,
        'c5' => false,
    ])
    ->andWhere('c3', 1)
    ->andWhere('c3', [1, 2, 3])
    ->andWhere(
        ['t1.first_name', 't1.last_name'], 
        [
            ['first_name' => 'a', 'last_name' => 'b'], 
            ['first_name' => 'c', 'last_name' => 'd'],
        ]
    )
    ->andWhere([
        'BETWEEN',
        't1.create_time',
        strtotime('20160101'),
        time()
    ])
    ->andWhere('c6', 'like', 'abc')
    ->andWhere('t1.id', (new SelectBuilder('mysql'))->select('id')->from('table3'))
    ->result();
```

`echo $sql;` will print:

```sql
SELECT
	` c1 `, ` c2 ` AS ` 2c `, ` c3 `, ` c4 `, 1 + 1
FROM
	` table1 ` ` t1 `
INNER JOIN ` table2 ` ` t2 ` ON t1. ID = t2.t1_id
WHERE
	(
		(
			(
				(
					(
						(
							(` c1 ` = : qb0)
							AND (` c2 ` IN(: qb1, : qb2))
							AND (` c4 ` IS NULL)
							AND (` c5 ` = FALSE)
						)
						AND (` c3 ` = : qb3)
					)
					AND (` c3 ` IN(: qb4, : qb5, : qb6))
				)
				AND (
					(
						` t1 `.` first_name `, ` t1 `.` last_name `
					) IN ((: qb7, : qb8),(: qb9, : qb10))
				)
			)
			AND (
				` t1 `.` create_time ` BETWEEN : qb11
				AND : qb12
			)
		)
		AND (` c6 ` LIKE : qb13)
	)
AND (
	` t1 `.` ID ` IN (SELECT ` ID ` FROM ` table3 `)
)
```

```php
print_r($params);
/*
Array(
(
    [:qb0] => 1
    [:qb1] => v1
    [:qb2] => v2
    [:qb3] => 1
    [:qb4] => 1
    [:qb5] => 2
    [:qb6] => 3
    [:qb7] => a
    [:qb8] => b
    [:qb9] => c
    [:qb10] => d
    [:qb11] => 1451577600
    [:qb12] => 1463670180
    [:qb13] => %abc%
)
*/
```
## Docs

todo
