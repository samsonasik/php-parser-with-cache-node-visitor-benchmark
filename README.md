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

<img width="964" height="195" alt="Image" src="https://github.com/user-attachments/assets/a1c139af-a4aa-429a-a1c1-676b2bb32bd4" />