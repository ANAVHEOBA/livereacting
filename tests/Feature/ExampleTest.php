<?php

test('health check endpoint is available', function () {
    $this->get('/up')->assertOk();
});
