<?php

namespace RTippin\Messenger\Tests\Http;

use RTippin\Messenger\Actions\BaseMessengerAction;
use RTippin\Messenger\Models\Participant;
use RTippin\Messenger\Models\Thread;
use RTippin\Messenger\Tests\FeatureTestCase;

class AddParticipantsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        BaseMessengerAction::disableEvents();
    }

    /** @test */
    public function forbidden_to_view_add_participants_on_private_thread()
    {
        $private = Thread::factory()->create();
        Participant::factory()->for($private)->owner($this->tippin)->create();
        $this->actingAs($this->tippin);

        $this->getJson(route('api.messenger.threads.add.participants', [
            'thread' => $private->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function non_participant_forbidden_to_view_add_participants()
    {
        $group = Thread::factory()->group()->create();
        $this->actingAs($this->developers);

        $this->getJson(route('api.messenger.threads.add.participants', [
            'thread' => $group->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function non_admin_without_permission_forbidden_to_view_add_participants()
    {
        $group = Thread::factory()->group()->create();
        Participant::factory()->for($group)->owner($this->doe)->create();
        $this->actingAs($this->doe);

        $this->getJson(route('api.messenger.threads.add.participants', [
            'thread' => $group->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function admin_forbidden_to_view_add_participants_when_disabled_from_settings()
    {
        $group = Thread::factory()->group()->create(['add_participants' => false]);
        Participant::factory()->for($group)->owner($this->tippin)->admin()->create();
        $this->actingAs($this->tippin);

        $this->getJson(route('api.messenger.threads.add.participants', [
            'thread' => $group->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function admin_forbidden_to_view_add_participants_when_thread_locked()
    {
        $group = Thread::factory()->group()->locked()->create();
        Participant::factory()->for($group)->owner($this->tippin)->admin()->create();
        $this->actingAs($this->tippin);

        $this->getJson(route('api.messenger.threads.add.participants', [
            'thread' => $group->id,
        ]))
            ->assertForbidden();
    }

    /** @test */
    public function non_admin_with_permission_can_view_add_participants()
    {
        $group = Thread::factory()->group()->create();
        Participant::factory()->for($group)->owner($this->doe)->create(['add_participants' => true]);
        $this->actingAs($this->doe);

        $this->getJson(route('api.messenger.threads.add.participants', [
            'thread' => $group->id,
        ]))
            ->assertSuccessful();
    }

    /** @test */
    public function admin_can_view_add_participants()
    {
        $group = Thread::factory()->group()->create();
        Participant::factory()->for($group)->owner($this->tippin)->admin()->create();
        $this->createFriends($this->tippin, $this->doe);
        $this->actingAs($this->tippin);

        $this->getJson(route('api.messenger.threads.add.participants', [
            'thread' => $group->id,
        ]))
            ->assertSuccessful()
            ->assertJsonCount(1)
            ->assertJson([
                [
                    'party' => [
                        'name' => 'John Doe',
                    ],
                    'party_id' => $this->doe->getKey(),
                ],
            ]);
    }

    /** @test */
    public function admin_can_add_many_participants()
    {
        $group = Thread::factory()->group()->create();
        Participant::factory()->for($group)->owner($this->tippin)->admin()->create();
        $this->createFriends($this->tippin, $this->doe);
        $this->createFriends($this->tippin, $this->developers);
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.participants.store', [
            'thread' => $group->id,
        ]), [
            'providers' => [
                [
                    'id' => $this->doe->getKey(),
                    'alias' => 'user',
                ],
                [
                    'id' => $this->developers->getKey(),
                    'alias' => 'company',
                ],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonCount(2);
    }

    /** @test */
    public function admin_forbidden_to_add_many_participants_when_thread_locked()
    {
        $group = Thread::factory()->group()->locked()->create();
        Participant::factory()->for($group)->owner($this->tippin)->admin()->create();
        $this->createFriends($this->tippin, $this->doe);
        $this->createFriends($this->tippin, $this->developers);
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.participants.store', [
            'thread' => $group->id,
        ]), [
            'providers' => [
                [
                    'id' => $this->doe->getKey(),
                    'alias' => 'user',
                ],
                [
                    'id' => $this->developers->getKey(),
                    'alias' => 'company',
                ],
            ],
        ])
            ->assertForbidden();
    }

    /** @test */
    public function non_admin_with_permission_can_add_participants()
    {
        $group = Thread::factory()->group()->create();
        Participant::factory()->for($group)->owner($this->doe)->create(['add_participants' => true]);
        $this->createFriends($this->doe, $this->developers);
        $this->actingAs($this->doe);

        $this->postJson(route('api.messenger.threads.participants.store', [
            'thread' => $group->id,
        ]), [
            'providers' => [
                [
                    'id' => $this->developers->getKey(),
                    'alias' => 'company',
                ],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonCount(1);
    }

    /** @test */
    public function non_friends_are_ignored_when_adding_participants()
    {
        $group = Thread::factory()->group()->create();
        Participant::factory()->for($group)->owner($this->tippin)->admin()->create();
        $this->createFriends($this->tippin, $this->doe);
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.participants.store', [
            'thread' => $group->id,
        ]), [
            'providers' => [
                [
                    'id' => $this->doe->getKey(),
                    'alias' => 'user',
                ],
                [
                    'id' => $this->developers->getKey(),
                    'alias' => 'company',
                ],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonCount(1);
    }

    /** @test */
    public function no_participants_added_when_no_friends_found()
    {
        $group = Thread::factory()->group()->create();
        Participant::factory()->for($group)->owner($this->tippin)->admin()->create();
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.participants.store', [
            'thread' => $group->id,
        ]), [
            'providers' => [
                [
                    'id' => $this->doe->getKey(),
                    'alias' => 'user',
                ],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonCount(0);
    }

    /** @test */
    public function existing_participant_will_be_ignored_when_adding_participants()
    {
        $group = Thread::factory()->group()->create();
        Participant::factory()->for($group)->owner($this->tippin)->admin()->create();
        Participant::factory()->for($group)->owner($this->doe)->create();
        $this->createFriends($this->tippin, $this->doe);

        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.participants.store', [
            'thread' => $group->id,
        ]), [
            'providers' => [
                [
                    'id' => $this->doe->getKey(),
                    'alias' => 'user',
                ],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonCount(0);
    }

    /**
     * @test
     * @dataProvider providersValidation
     * @param $providers
     * @param $errors
     */
    public function add_participants_checks_providers($providers, $errors)
    {
        $group = Thread::factory()->group()->create();
        Participant::factory()->for($group)->owner($this->tippin)->admin()->create();
        $this->actingAs($this->tippin);

        $this->postJson(route('api.messenger.threads.participants.store', [
            'thread' => $group->id,
        ]), [
            'providers' => $providers,
        ])
            ->assertJsonValidationErrors($errors);
    }

    public function providersValidation(): array
    {
        return [
            'Alias and ID cannot be null' => [
                [['alias' => null, 'id' => null]],
                ['providers.0.alias', 'providers.0.id'],
            ],
            'Alias and ID cannot be empty' => [
                [['alias' => '', 'id' => '']],
                ['providers.0.alias', 'providers.0.id'],
            ],
            'Alias must be a string' => [
                [['alias' => 123, 'id' => 1]],
                ['providers.0.alias'],
            ],
            'Providers array cannot be empty' => [
                [[]],
                ['providers.0.alias', 'providers.0.id'],
            ],
            'Validates all items in the array' => [
                [['alias' => 'user', 'id' => 1], ['alias' => null, 'id' => null]],
                ['providers.1.alias', 'providers.1.id'],
            ],
        ];
    }
}
