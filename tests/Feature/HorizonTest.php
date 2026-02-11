<?php

it('renders the horizon dashboard in local environment', function () {
    $response = $this->get('/horizon');

    $response->assertSuccessful();
});
