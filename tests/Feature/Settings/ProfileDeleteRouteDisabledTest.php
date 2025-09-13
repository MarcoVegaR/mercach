<?php

it('disables DELETE /settings/profile', function () {
    // No auth needed for a 405 on wrong method
    $response = $this->delete('/settings/profile');

    // Method not allowed since GET/PATCH exist but DELETE is disabled
    $response->assertStatus(405);
});
