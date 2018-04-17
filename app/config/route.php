<?php

$app->get('/api/test', 'Test', ['authentication:t1:t2']);

$app->get('/api/users_y(\d+)_m(\d+)_d(\d+)', 'Test@say', ['authentication:a1:a2:a3']);

