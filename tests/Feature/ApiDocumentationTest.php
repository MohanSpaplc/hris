<?php

test('swagger docs are hidden when disabled', function () {
    config(['hris.api_docs_enabled' => false]);

    $this->get('/api/documentation')->assertNotFound();
});

test('swagger docs are served when enabled', function () {
    config(['hris.api_docs_enabled' => true]);

    $this->get('/api/documentation')->assertOk();
});
