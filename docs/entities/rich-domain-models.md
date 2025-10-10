# Rich Domain Models

In the datamapper pattern, entities are your **domain models** - they contain business logic, enforce business rules, and represent concepts in your problem domain. Unlike Active Record where models are thin data containers, datamapper entities should be **rich with behavior**.

This guide shows how to build entities that truly model your business domain.

## Table of Contents

- [What Makes a Domain Model "Rich"?](#what-makes-a-domain-model-rich)
- [Anemic vs Rich Domain Models](#anemic-vs-rich-domain-models)
- [Real-World Example: ServiceJob Entity](#real-world-example-servicejob-entity)
- [Key Patterns for Rich Domain Models](#key-patterns-for-rich-domain-models)
  - [Pattern 1: State Transitions with Validation](#pattern-1-state-transitions-with-validation)
  - [Pattern 2: Business Rules as Methods](#pattern-2-business-rules-as-methods)
  - [Pattern 3: Collections as Domain Concepts](#pattern-3-collections-as-domain-concepts)
  - [Pattern 4: Domain Exceptions](#pattern-4-domain-exceptions)
  - [Pattern 5: Domain Events](#pattern-5-domain-events)
  - [Pattern 6: Specification Pattern](#pattern-6-specification-pattern)
- [Separation of Concerns](#separation-of-concerns)
- [Testing Rich Domain Models](#testing-rich-domain-models)
- [Summary](#summary)

## What Makes a Domain Model "Rich"?

A rich domain model:

- ✅ **Encapsulates state** - Internal data is protected
- ✅ **Exposes behavior** - Methods represent domain actions
- ✅ **Enforces invariants** - Business rules always maintained
- ✅ **Uses domain language** - Methods named after business concepts
- ✅ **Prevents invalid states** - Impossible to create invalid objects
- ✅ **Tells a story** - Reading the code explains the business

## Anemic vs Rich Domain Models

### ❌ Anemic Model (Avoid This)

```php
// This is just a data bag - no behavior!
class Order
{
    public int $id;
    public string $status;
    public Money $total;
    public array $items;
    public Chronos $created_at;
}

// Business logic leaked into service layer
class OrderService
{
    public function placeOrder(Order $order): void
    {
        // Business rules in service, not entity
        if (count($order->items) === 0) {
            throw new Exception('Cannot place empty order');
        }
        
        if ($order->total->greaterThan(Money::USD(10000))) {
            $order->status = 'requires_approval';
        } else {
            $order->status = 'placed';
        }
        
        $orderMapper->save($order);
    }
}
```

**Problems:**
- Entity is just a data bag
- Business logic scattered across services
- Easy to create invalid states
- Hard to find where rules are enforced
- Domain language hidden in services

### ✅ Rich Domain Model (Do This)

```php
class Order extends Entity
{
    protected OrderStatus $status;
    protected Money $total;
    protected Collection $items;
    protected Chronos $created_at;
    protected ?Chronos $approved_at = null;
    
    /**
     * Constructor enforces invariants
     */
    public function __construct(Customer $customer)
    {
        $this->customer_id = $customer->id;
        $this->status = OrderStatus::Draft;
        $this->items = collect();
        $this->total = Money::USD(0);
        $this->created_at = Chronos::now();
    }
    
    /**
     * Domain method: Add item
     */
    public function addItem(Product $product, int $quantity): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw new DomainException('Cannot modify placed order');
        }
        
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }
        
        $this->items->push(new OrderItem($product, $quantity));
        $this->recalculateTotal();
    }
    
    /**
     * Domain method: Place order
     */
    public function place(): void
    {
        if ($this->items->isEmpty()) {
            throw new DomainException('Cannot place empty order');
        }
        
        if ($this->requiresApproval()) {
            $this->status = OrderStatus::RequiresApproval;
        } else {
            $this->status = OrderStatus::Placed;
        }
        
        event(new OrderPlaced($this));
    }
    
    /**
     * Domain method: Approve order
     */
    public function approve(User $approver): void
    {
        if ($this->status !== OrderStatus::RequiresApproval) {
            throw new DomainException('Only pending orders can be approved');
        }
        
        $this->status = OrderStatus::Approved;
        $this->approved_by_id = $approver->id;
        $this->approved_at = Chronos::now();
        
        event(new OrderApproved($this, $approver));
    }
    
    /**
     * Business rule: Large orders require approval
     */
    protected function requiresApproval(): bool
    {
        return $this->total->greaterThan(Money::USD(10000));
    }
    
    /**
     * Business logic: Calculate total
     */
    protected function recalculateTotal(): void
    {
        $this->total = $this->items->reduce(
            fn($carry, $item) => $carry->add($item->getSubtotal()),
            Money::USD(0)
        );
    }
}

// Service layer is now thin
class OrderService
{
    public function placeOrder(Order $order): void
    {
        // Just coordinate - business logic in entity
        $order->place();
        $this->orderMapper->save($order);
        $this->notifyCustomer($order);
    }
}
```

**Benefits:**
- Business logic lives in entity
- Impossible to create invalid order
- Domain language throughout
- Easy to find and test business rules
- Self-documenting

## Real-World Example: ServiceJob Entity

Here's a production entity from a production application showing rich domain modeling:

```php
class ServiceJob extends Entity implements CalendarEventable
{
    use HasTenant;

    // Domain-specific constants
    const STATUS_UNCONFIRMED = 'unconfirmed';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_IN_PROGRESS = 'in-progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELED = 'canceled';

    // Protected state
    protected int $service_id;
    protected ?Service $service = null;
    protected string $status;
    protected string $number;
    protected Chronos $scheduled_start_date;
    protected ?Chronos $estimated_completion_date = null;
    protected ?Chronos $actual_completion_date = null;
    protected float $current_progress = 0;
    protected ?Collection $assignedTechnicians = null;
    protected ?Chronos $billed_on = null;
    protected ?int $canceled_by_id = null;
    protected ?Chronos $canceled_at = null;
    
    /**
     * Constructor for creating NEW service jobs
     */
    public function __construct(
        Service $service,
        ?Chronos $scheduled_start_date
    ) {
        $this->tenant_id = $service->tenant_id;
        $this->service_id = $service->id;
        $this->service = $service;
        $this->number = $this->generateJobNumber();
        $this->scheduled_start_date = $scheduled_start_date;
        
        // Business rule: Auto-confirm if service doesn't require it
        if ($service->requires_confirmation) {
            $this->status = self::STATUS_UNCONFIRMED;
        } else {
            $this->status = self::STATUS_CONFIRMED;
        }
        
        // Calculate estimated completion
        $this->estimated_completion_date = $this->scheduled_start_date
            ->addMinutes($service->duration->inMinutes());
    }
    
    /**
     * Domain method: Assign technician
     * @throws CouldNotAssign
     */
    public function assign(User $assignedUser, User $assignedBy): void
    {
        // Business rule: Can't assign to completed jobs
        if ($this->status === self::STATUS_COMPLETED) {
            throw CouldNotAssign::serviceJobHasAlreadyBeenCompleted($assignedUser, $this);
        }
        
        // Idempotent: Already assigned = noop
        if ($this->assignedTechnicians->contains($assignedUser)) {
            return;
        }
        
        $this->assignedTechnicians->add($assignedUser);
        
        // Domain event
        event(new UserWasAssigned($this, $assignedUser, $assignedBy));
    }
    
    /**
     * Domain method: Start job
     * @throws CouldNotStart
     */
    public function start(User $user): void
    {
        // Business rule: Must be confirmed
        if ($this->status !== self::STATUS_CONFIRMED) {
            throw CouldNotStart::invalidStatus();
        }
        
        // Business rule: Must have assigned technicians
        if (!$this->assignedTechnicians->count()) {
            throw CouldNotStart::noAssignedTechnicians();
        }
        
        $this->status = self::STATUS_IN_PROGRESS;
        
        event(new JobWasStartedByUser($this, $user));
    }
    
    /**
     * Domain method: Complete job
     * @throws CouldNotComplete
     */
    public function complete(User $user): void
    {
        // Business rule: Must be in progress
        if ($this->status !== self::STATUS_IN_PROGRESS) {
            throw CouldNotComplete::invalidStatus($this);
        }
        
        $this->status = self::STATUS_COMPLETED;
        $this->completed_by_id = $user->id;
        $this->actual_completion_date = Chronos::now();
        
        event(new JobWasCompletedByUser($this, $user));
    }
    
    /**
     * Domain method: Cancel job
     * @throws CouldNotCancel
     */
    public function cancel(User $user): void
    {
        // Business rule: Can't cancel completed jobs
        if ($this->status === self::STATUS_COMPLETED) {
            throw CouldNotCancel::serviceJobHasAlreadyBeenCompleted($this);
        }
        
        // Idempotent: Already canceled = noop
        if ($this->status === self::STATUS_CANCELED) {
            return;
        }
        
        $this->status = self::STATUS_CANCELED;
        $this->canceled_by_id = $user->id;
        $this->canceled_at = Chronos::now();
        
        event(new JobWasCanceledByUser($this, $user));
    }
    
    /**
     * Business logic: Generate unique job number
     */
    protected function generateJobNumber(): string
    {
        $prefix = Date::now()->format('YmdHis');
        $suffix = mt_rand(10000, 99999);
        
        return "JOB-{$prefix}-{$suffix}";
    }
    
    /**
     * Implements CalendarEventable interface
     */
    public function getCalendarTitle(): string
    {
        return $this->service->name;
    }
    
    public function getCalendarColorClass(): string
    {
        return match ($this->status) {
            self::STATUS_UNCONFIRMED => 'bg-gray-50',
            self::STATUS_CONFIRMED => 'bg-blue-50',
            self::STATUS_IN_PROGRESS => 'bg-yellow-50',
            self::STATUS_COMPLETED => 'bg-green-50',
            self::STATUS_CANCELED => 'bg-red-50',
            default => 'bg-gray-50',
        };
    }
}
```

**Notice:**
- Methods named after business actions (assign, start, complete, cancel)
- Business rules enforced (can't start without technicians)
- Domain exceptions with context
- Domain events dispatched
- Idempotent operations (already canceled = noop)
- Interfaces for cross-cutting concerns (CalendarEventable)

## Key Patterns for Rich Domain Models

### Pattern 1: State Transitions with Validation

```php
class Invoice extends Entity
{
    protected InvoiceStatus $status;
    protected Money $total;
    protected ?Chronos $sent_at = null;
    protected ?Chronos $paid_at = null;
    
    public function __construct(Client $client, Money $total)
    {
        $this->client_id = $client->id;
        $this->total = $total;
        $this->status = InvoiceStatus::Draft;
    }
    
    /**
     * State transition: Draft → Sent
     */
    public function send(): void
    {
        if ($this->status !== InvoiceStatus::Draft) {
            throw new DomainException('Only draft invoices can be sent');
        }
        
        if ($this->total->isZero()) {
            throw new DomainException('Cannot send zero-amount invoice');
        }
        
        $this->status = InvoiceStatus::Sent;
        $this->sent_at = Chronos::now();
        
        event(new InvoiceSent($this));
    }
    
    /**
     * State transition: Sent → Paid
     */
    public function markAsPaid(Money $paidAmount, Chronos $paidOn): void
    {
        if ($this->status !== InvoiceStatus::Sent) {
            throw new DomainException('Only sent invoices can be marked as paid');
        }
        
        if (!$paidAmount->equals($this->total)) {
            throw new DomainException('Paid amount must equal invoice total');
        }
        
        $this->status = InvoiceStatus::Paid;
        $this->paid_at = $paidOn;
        
        event(new InvoicePaid($this, $paidAmount));
    }
    
    /**
     * State query
     */
    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }
    
    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Sent 
            && $this->due_date->isPast();
    }
}
```

### Pattern 2: Business Rules as Methods

```php
class Client extends Entity
{
    protected Money $total_revenue;
    protected Money $outstanding_balance;
    protected ClientStatus $status;
    protected ?Chronos $last_service_date = null;
    
    /**
     * Business rule: VIP status
     */
    public function isVIP(): bool
    {
        return $this->total_revenue->greaterThan(Money::USD(50000));
    }
    
    /**
     * Business rule: Payment terms
     */
    public function getPaymentTermsDays(): int
    {
        if ($this->isVIP()) {
            return 60; // VIPs get extended terms
        }
        
        if ($this->hasGoodPaymentHistory()) {
            return 30;
        }
        
        return 15; // Default net-15
    }
    
    /**
     * Business rule: Can schedule service
     */
    public function canScheduleService(): bool
    {
        if ($this->status === ClientStatus::Inactive) {
            return false;
        }
        
        if ($this->outstanding_balance->greaterThan(Money::USD(10000))) {
            return false; // Too much debt
        }
        
        return true;
    }
    
    /**
     * Domain logic: Calculate at-risk score
     */
    public function getAtRiskScore(): int
    {
        $score = 0;
        
        if ($this->outstanding_balance->greaterThan($this->total_revenue)) {
            $score += 50;
        }
        
        if ($this->last_service_date && $this->last_service_date->isPast(Chronos::now()->subYears(2))) {
            $score += 30;
        }
        
        if ($this->status === ClientStatus::Suspended) {
            $score += 20;
        }
        
        return $score;
    }
}
```

### Pattern 3: Collections as Domain Concepts

```php
class Estimate extends Entity
{
    protected Collection $lineItems;
    protected Money $subtotal;
    protected Money $tax;
    protected Money $total;
    
    public function __construct(Client $client)
    {
        $this->client_id = $client->id;
        $this->lineItems = collect();
        $this->subtotal = Money::USD(0);
        $this->tax = Money::USD(0);
        $this->total = Money::USD(0);
    }
    
    /**
     * Domain method: Add line item
     */
    public function addLineItem(
        string $description,
        Money $price,
        int $quantity = 1
    ): void {
        $lineItem = new EstimateLineItem($description, $price, $quantity);
        $this->lineItems->push($lineItem);
        
        $this->recalculate();
    }
    
    /**
     * Domain method: Remove line item
     */
    public function removeLineItem(int $index): void
    {
        $this->lineItems->forget($index);
        $this->recalculate();
    }
    
    /**
     * Domain method: Apply discount
     */
    public function applyDiscount(Money $discount): void
    {
        if ($discount->greaterThan($this->subtotal)) {
            throw new DomainException('Discount cannot exceed subtotal');
        }
        
        $this->subtotal = $this->subtotal->subtract($discount);
        $this->recalculate();
    }
    
    /**
     * Business logic: Recalculate totals
     */
    protected function recalculate(): void
    {
        $this->subtotal = $this->lineItems->reduce(
            fn($carry, $item) => $carry->add($item->getTotal()),
            Money::USD(0)
        );
        
        $this->tax = $this->calculateTax();
        $this->total = $this->subtotal->add($this->tax);
    }
    
    /**
     * Business rule: Tax calculation
     */
    protected function calculateTax(): Money
    {
        $taxRate = $this->client->tax_rate ?? 0.0;
        $taxAmount = $this->subtotal->getAmount() * $taxRate;
        
        return new Money((int) $taxAmount, $this->subtotal->getCurrency());
    }
}
```

### Pattern 4: Domain Exceptions

Create specific exceptions that speak the domain language:

```php
namespace App\Exceptions\ServiceJob;

class CouldNotStart extends \DomainException
{
    public static function invalidStatus(ServiceJob $job): self
    {
        return new self(
            "Cannot start service job #{$job->number}. " .
            "Job must be confirmed before starting. " .
            "Current status: {$job->status}"
        );
    }
    
    public static function noAssignedTechnicians(): self
    {
        return new self(
            'Cannot start service job without assigned technicians. ' .
            'Please assign at least one technician first.'
        );
    }
    
    public static function alreadyStarted(ServiceJob $job): self
    {
        return new self(
            "Service job #{$job->number} has already been started."
        );
    }
}

// Usage
try {
    $serviceJob->start($user);
} catch (CouldNotStart $e) {
    // Exception message is helpful and specific
    return response()->json(['error' => $e->getMessage()], 422);
}
```

### Pattern 5: Domain Events

Dispatch events for important business moments:

```php
class Order extends Entity
{
    public function place(): void
    {
        $this->validateCanBePlaced();
        
        $this->status = OrderStatus::Placed;
        $this->placed_at = Chronos::now();
        
        // Domain event
        event(new OrderPlaced($this));
    }
    
    public function ship(): void
    {
        $this->validateCanBeShipped();
        
        $this->status = OrderStatus::Shipped;
        $this->shipped_at = Chronos::now();
        
        // Domain event
        event(new OrderShipped($this));
    }
}

// Event handler (in a listener or subscriber)
class SendOrderConfirmationEmail
{
    public function handle(OrderPlaced $event): void
    {
        Mail::to($event->order->customer->email)
            ->send(new OrderConfirmation($event->order));
    }
}
```

### Pattern 6: Specification Pattern

Complex business rules as objects:

```php
interface Specification
{
    public function isSatisfiedBy($candidate): bool;
}

class ClientCanScheduleService implements Specification
{
    public function isSatisfiedBy($client): bool
    {
        return $client->status === ClientStatus::Active
            && $client->outstanding_balance->lessThan(Money::USD(10000))
            && !$client->hasUnresolvedComplaints();
    }
}

class ClientIsVIP implements Specification
{
    public function isSatisfiedBy($client): bool
    {
        return $client->total_revenue->greaterThan(Money::USD(50000))
            && $client->hasGoodPaymentHistory()
            && $client->yearsAsCustomer() >= 3;
    }
}

// Usage
$canSchedule = new ClientCanScheduleService();
$isVIP = new ClientIsVIP();

if (!$canSchedule->isSatisfiedBy($client)) {
    throw new DomainException('Client cannot schedule services');
}

if ($isVIP->isSatisfiedBy($client)) {
    $discount = 0.10; // VIP discount
}
```

## Separation of Concerns

### Entity Responsibilities (✅ Do)

- ✅ **Business logic** - Rules about the entity
- ✅ **State transitions** - How entity changes
- ✅ **Validation** - Enforce invariants
- ✅ **Calculations** - Derive values from state
- ✅ **Domain events** - Signal business moments
- ✅ **Behavior** - Actions the entity can perform

### Entity Shouldn't Do (❌ Don't)

- ❌ **Persistence** - Save to database
- ❌ **Queries** - Fetch other entities
- ❌ **External services** - Call APIs
- ❌ **Email/notifications** - Send messages
- ❌ **Authorization** - Check permissions
- ❌ **Framework concerns** - HTTP, routing, etc.

### Example: Good Separation

```php
// ✅ Entity: Business logic only
class Order extends Entity
{
    public function place(): void
    {
        $this->validateCanBePlaced();
        $this->status = OrderStatus::Placed;
        event(new OrderPlaced($this));
    }
    
    protected function validateCanBePlaced(): void
    {
        if ($this->items->isEmpty()) {
            throw new DomainException('Cannot place empty order');
        }
    }
}

// ✅ Service: Coordination and persistence
class PlaceOrderService
{
    public function __construct(
        private OrderMapper $orders,
        private InventoryService $inventory,
        private PaymentGateway $payments
    ) {}
    
    public function execute(Order $order, PaymentMethod $payment): void
    {
        // Check inventory
        $this->inventory->reserve($order->items);
        
        // Process payment
        $this->payments->charge($order->total, $payment);
        
        // Place order (business logic in entity)
        $order->place();
        
        // Persist
        $this->orders->save($order);
        
        // Notify (event listener will handle this)
    }
}

// ✅ Event Listener: Side effects
class SendOrderConfirmation
{
    public function handle(OrderPlaced $event): void
    {
        Mail::to($event->order->customer->email)
            ->send(new OrderConfirmation($event->order));
    }
}
```

## Testing Rich Domain Models

Rich domain models are easy to test because they're isolated:

```php
class ServiceJobTest extends TestCase
{
    public function test_can_start_confirmed_job_with_assigned_technician()
    {
        // Arrange
        $service = $this->createService();
        $technician = $this->createUser();
        $job = new ServiceJob($service, Chronos::now());
        $job->confirm($this->createUser());
        $job->assign($technician, $this->createUser());
        
        // Act
        $job->start($technician);
        
        // Assert
        $this->assertEquals(ServiceJob::STATUS_IN_PROGRESS, $job->status);
    }
    
    public function test_cannot_start_unconfirmed_job()
    {
        // Arrange
        $service = $this->createService();
        $job = new ServiceJob($service, Chronos::now());
        
        // Act & Assert
        $this->expectException(CouldNotStart::class);
        $this->expectExceptionMessage('must be confirmed');
        
        $job->start($this->createUser());
    }
    
    public function test_cannot_start_job_without_technicians()
    {
        // Arrange
        $service = $this->createService();
        $job = new ServiceJob($service, Chronos::now());
        $job->confirm($this->createUser());
        
        // Act & Assert
        $this->expectException(CouldNotStart::class);
        $this->expectExceptionMessage('assigned technicians');
        
        $job->start($this->createUser());
    }
}
```

**No database required!** Pure unit tests for business logic.

## Summary

Rich domain models:

1. **Encapsulate business logic** in the entities themselves
2. **Use domain language** in method names and concepts
3. **Enforce business rules** through validation and state transitions
4. **Prevent invalid states** through careful design
5. **Dispatch domain events** for important business moments
6. **Separate concerns** - entities focus on business, not infrastructure
7. **Are easy to test** - no database dependencies

The datamapper pattern enables this by keeping persistence concerns completely separate from your domain model.

## Next Steps

- **[Entity Lifecycle](../core-concepts/entity-lifecycle.md)** - Creation vs hydration
- **[Entity Patterns](../entity-patterns.md)** - Different entity structures
- **[Value Objects](../core-concepts/value-objects.md)** - Building blocks for rich models
- **[Creating Mappers](../mappers/creating-mappers.md)** - Persistence for rich models

## Further Reading

- Eric Evans: *Domain-Driven Design* (The blue book)
- Vaughn Vernon: *Implementing Domain-Driven Design* (The red book)
- Martin Fowler: [Anemic Domain Model](https://martinfowler.com/bliki/AnemicDomainModel.html)
