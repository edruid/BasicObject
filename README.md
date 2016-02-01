# BasicObject
Yet anoyther object relational mapper

# Performance hint:

This object relational mapper makes heavy use of `information_schema` in mysql,
per default queries to this schema triggers recalculation of statistics every
time it is queried which slows things down a lot. Try turning
`innodb_stats_on_metadata` of to stop mysql doing this.

```set global innodb_stats_on_metadata=0;```

There are forks of this project solving this with a memcached instance caching
the schema information instead.
