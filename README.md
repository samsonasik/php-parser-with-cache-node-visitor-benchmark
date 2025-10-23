# php-parser-with-cache-node-visitor-benchmark

**For non-cached:**

```
cd src/WithoutCache
composer install
php ../test.php

php-parser visitor benchmark
Mode            : WithoutCache
------------------------------------------------------------
Nodes visited   : 45,214,600
Total time      : 51,610.43 ms
Peak memory     : 2.18 GB
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
Nodes visited   : 45,214,600
Total time      : 48,756.19 ms
Peak memory     : 2.18 GB
============================================================
```

<img width="1009" height="207" alt="Image" src="https://github.com/user-attachments/assets/8475f29c-5e6a-4792-90ca-385e592e1063" />