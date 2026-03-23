<?php

it('redirects to admin panel', function () {
    $response = $this->get('/');

    $response->assertRedirect();
});
