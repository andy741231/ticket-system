{{ $heading }}

{{ $intro }}

@if(!empty($meta))
@foreach($meta as $label => $value)
{{ $label }}: {{ $value }}
@endforeach
@endif

@if(!empty($ticketUrl))
{{ $buttonText ?? 'View Ticket' }}: {{ $ticketUrl }}
@endif

@if(!empty($footer))

{{ $footer }}
@endif
