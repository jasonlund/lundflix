<?php

it('shows the lundbergh login hint bubble', function () {
    $response = $this->get(route('login'));

    $response
        ->assertOk()
        ->assertSee(__('lundbergh.form.email_description'))
        ->assertSee('lundbergh-head');
});
