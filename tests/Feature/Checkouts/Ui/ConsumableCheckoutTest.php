<?php

namespace Tests\Feature\Checkouts\Ui;

use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\User;
use App\Notifications\CheckoutConsumableNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ConsumableCheckoutTest extends TestCase
{
    public function testCheckingOutConsumableRequiresCorrectPermission(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('consumables.checkout.store', Consumable::factory()->create()))
            ->assertForbidden();
    }

    public function testValidationWhenCheckingOutConsumable(): void
    {
        $this->actingAs(User::factory()->checkoutConsumables()->create())
            ->post(route('consumables.checkout.store', Consumable::factory()->create()), [
                // missing assigned_to
            ])
            ->assertSessionHas('error');
    }

    public function testConsumableMustBeAvailableWhenCheckingOut(): void
    {
        $this->actingAs(User::factory()->checkoutConsumables()->create())
            ->post(route('consumables.checkout.store', Consumable::factory()->withoutItemsRemaining()->create()), [
                'assigned_to' => User::factory()->create()->id,
            ])
            ->assertSessionHas('error');
    }

    public function testConsumableCanBeCheckedOut(): void
    {
        $consumable = Consumable::factory()->create();
        $user = User::factory()->create();

        $this->actingAs(User::factory()->checkoutConsumables()->create())
            ->post(route('consumables.checkout.store', $consumable), [
                'assigned_to' => $user->id,
            ]);

        $this->assertTrue($user->consumables->contains($consumable));
    }

    public function testUserSentNotificationUponCheckout(): void
    {
        Notification::fake();

        $consumable = Consumable::factory()->create();
        $user = User::factory()->create();

        $this->actingAs(User::factory()->checkoutConsumables()->create())
            ->post(route('consumables.checkout.store', $consumable), [
                'assigned_to' => $user->id,
            ]);

        Notification::assertSentTo($user, CheckoutConsumableNotification::class);
    }

    public function testActionLogCreatedUponCheckout(): void
    {
        $consumable = Consumable::factory()->create();
        $actor = User::factory()->checkoutConsumables()->create();
        $user = User::factory()->create();

        $this->actingAs($actor)
            ->post(route('consumables.checkout.store', $consumable), [
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

    public function testConsumableCheckoutPagePostIsRedirectedIfRedirectSelectionIsIndex(): void
    {
        $consumable = Consumable::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('consumables.index'))
            ->post(route('consumables.checkout.store', $consumable), [
                'assigned_to' =>  User::factory()->create()->id,
                'redirect_option' => 'index',
                'assigned_qty' => 1,
            ])
            ->assertStatus(302)
            ->assertRedirect(route('consumables.index'));
    }

    public function testConsumableCheckoutPagePostIsRedirectedIfRedirectSelectionIsItem(): void
    {
        $consumable = Consumable::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('consumables.index'))
            ->post(route('consumables.checkout.store' , $consumable), [
                'assigned_to' =>  User::factory()->create()->id,
                'redirect_option' => 'item',
                'assigned_qty' => 1,
            ])
            ->assertStatus(302)
            ->assertRedirect(route('consumables.show', ['consumable' => $consumable->id]));
    }

    public function testConsumableCheckoutPagePostIsRedirectedIfRedirectSelectionIsTarget(): void
    {
        $user = User::factory()->create();
        $consumable = Consumable::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('components.index'))
            ->post(route('consumables.checkout.store' , $consumable), [
                'assigned_to' =>  $user->id,
                'redirect_option' => 'target',
                'assigned_qty' => 1,
            ])
            ->assertStatus(302)
            ->assertRedirect(route('users.show', ['user' => $user]));
    }

}
