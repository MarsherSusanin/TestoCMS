@include('booking-module::public.partials.styles')
@include('booking-module::public.partials.booking-form', ['service' => $service, 'showHeading' => (bool) ($config['show_heading'] ?? true)])
