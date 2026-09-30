<?php

test('the root route directs users to the dashboard', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('dashboard'));
});
