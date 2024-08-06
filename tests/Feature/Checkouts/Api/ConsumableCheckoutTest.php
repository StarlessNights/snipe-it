<?php

namespace Tests\Feature\Checkouts\Api;

use App\Models\Actionlog;
use App\Models\Consumable;
use App\Models\User;
use App\Notifications\CheckoutConsumableNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ConsumableCheckoutTest extends TestCase
{
    public function testCheckingOutConsumableRequiresCorrectPermission(): void
    {
        $this->actingAsForApi(User::factory()->create())
            ->postJson(route('api.consumables.checkout', Consumable::factory()->create()))
            ->assertForbidden();
    }

    public function testValidationWhenCheckingOutConsumable(): void
    {
        $this->actingAsForApi(User::factory()->checkoutConsumables()->create())
            ->postJson(route('api.consumables.checkout', Consumable::factory()->create()), [
                // missing assigned_to
            ])
            ->assertStatusMessageIs('error');
    }

    public function testConsumableMustBeAvailableWhenCheckingOut(): void
    {
        $this->actingAsForApi(User::factory()->checkoutConsumables()->create())
            ->postJson(route('api.consumables.checkout', Consumable::factory()->withoutItemsRemaining()->create()), [
                'assigned_to' => User::factory()->create()->id,
            ])
            ->assertStatusMessageIs('error');
    }

    public function testConsumableCanBeCheckedOut(): void
    {
        $consumable = Consumable::factory()->create();
        $user = User::factory()->create();

        $this->actingAsForApi(User::factory()->checkoutConsumables()->create())
            ->postJson(route('api.consumables.checkout', $consumable), [
                'assigned_to' => $user->id,
            ]);

        $this->assertTrue($user->consumables->contains($consumable));
    }

    public function testUserSentNotificationUponCheckout(): void
    {
        Notification::fake();

        $consumable = Consumable::factory()->requiringAcceptance()->create();

        $user = User::factory()->create();

        $this->actingAsForApi(User::factory()->checkoutConsumables()->create())
            ->postJson(route('api.consumables.checkout', $consumable), [
                'assigned_to' => $user->id,
            ]);

        Notification::assertSentTo($user, CheckoutConsumableNotification::class);
    }

    public function testActionLogCreatedUponCheckout(): void
    {$consumable = Consumable::factory()->create();
        $actor = User::factory()->checkoutConsumables()->create();
        $user = User::factory()->create();

        $this->actingAsForApi($actor)
            ->postJson(route('api.consumables.checkout', $consumable), [
                'assigned_to' => $user->id,
                'note' => 'oh hi there',
            ]);

        $this->assertEquals(
            1,
            Actionlog::where([
                'action_type' => 'checkout',
                'target_id' => $user->id,
                'target_type' => User::class,
                'item_id' => $consumable->id,
                'item_type' => Consumable::class,
                'user_id' => $actor->id,
                'note' => 'oh hi there',
            ])->count(),
            'Log entry either does not exist or there are more than expected'
        );
    }
}
