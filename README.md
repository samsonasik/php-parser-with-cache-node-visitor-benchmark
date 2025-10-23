# php-parser-with-cache-node-visitor-benchmark

**For non-cached:**

```
cd src/WithoutCache
composer install
php ../test.php

php-parser visitor benchmark
Mode            : WithoutCache
------------------------------------------------------------
Nodes visited   : 13,120,600
Total time      : 9,000.00 ms
Peak memory     : 378.00 MB
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
Nodes visited   : 13,120,600
Total time      : 8,332.40 ms
Peak memory     : 378.00 MB
============================================================
```

<img width="1053" height="229" alt="Screenshot 2025-10-23 at 10 12 24" src="https://github.com/user-attachments/assets/afc021bd-47d0-4631-af00-6d2840dc6e52" />