---
trigger: manual
---

## Back End Developer Agent Rules

### Laravel Best Practices
- **MVC Architecture**: Maintain strict separation between Models, Views (Inertia responses), and Controllers
- **Eloquent Models**: Use proper relationships, accessors, mutators, and resource classes for API responses
- **Service Layer**: Implement service classes for complex business logic to keep controllers thin
- **Form Requests**: Always use Form Request classes for validation with proper rules and messages

### Database Design
- **Migrations**: Write reversible migrations with proper foreign key constraints and indexes
- **Seeders**: Provide comprehensive seeders for development and testing environments
- **Query Optimization**: Use eager loading, select specific columns, and implement database indexes
- **Schema Design**: Follow Laravel naming conventions and normalize data appropriately

### API Development for Inertia
```php
// Controller pattern for Inertia responses
public function index(): Response
{
    return Inertia::render('Users/Index', [
        'users' => UserResource::collection(
            User::query()
                ->with(['roles', 'permissions'])
                ->paginate(15)
        ),
        'filters' => request()->only(['search', 'status']),
    ]);
}
```

### Security Implementation
- **Authentication**: Implement Laravel Sanctum for SPA authentication
- **Authorization**: Use Gates and Policies for fine-grained access control
- **CSRF Protection**: Ensure all forms include CSRF tokens via Inertia
- **Input Validation**: Validate all inputs server-side with appropriate rules
- **SQL Injection**: Use Eloquent ORM and parameterized queries exclusively

### Performance Optimization
- **Caching Strategy**: Implement Redis for sessions, cache, and queue management
- **Database Queries**: Optimize N+1 queries and implement query scopes
- **Queue Management**: Use Laravel queues for time-intensive operations
- **Response Caching**: Cache expensive computations and database queries appropriately

### Testing Standards
- **Unit Tests**: Write tests for all service classes and model methods
- **Feature Tests**: Test all API endpoints and Inertia responses
- **Database Testing**: Use factories and refreshDatabase trait properly
- **Code Coverage**: Maintain minimum 80% test coverage for critical paths