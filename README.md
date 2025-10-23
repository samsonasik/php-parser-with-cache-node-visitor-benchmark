# php-parser-with-cache-node-visitor-benchmark

**For non-cached:**

```
cd src/WithoutCache
composer install
php ../test.php

php-parser visitor benchmark
Mode            : WithoutCache
------------------------------------------------------------
Nodes visited   : 43,782,000
Total time      : 38,186.66 ms
Peak memory     : 1.38 GB
============================================================
```

**For Cached:**

```
cd src/WithCache
composer install
php ../test.php

php-parser visitor benchmark
Mode            : WithCache
------------------------------------------------------------
Nodes visited   : 43,782,000
Total time      : 35,737.56 ms
Peak memory     : 1.38 GB
============================================================
```

<img width="1009" height="207" alt="Image" src="https://github.com/user-attachments/assets/8475f29c-5e6a-4792-90ca-385e592e1063" />