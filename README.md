# php-parser-with-cache-node-visitor-benchmark

**For non-cached:**

```
cd src/WithoutCache
composer install
php ../test.php

php-parser visitor benchmark
Mode            : WithoutCache
------------------------------------------------------------
Nodes visited   : 32,272,800
Total time      : 16,974.85 ms
Peak memory     : 182.00 MB
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
Nodes visited   : 32,272,800
Total time      : 10,218.84 ms
Peak memory     : 182.00 MB
============================================================
```

<img width="1012" height="205" alt="Image" src="https://github.com/user-attachments/assets/578b4857-a6a5-4b73-b898-b2ac95c8f373" />