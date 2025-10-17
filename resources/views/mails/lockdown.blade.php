<h1>{{ $heading }}</h1>
<p>{{ $msg }}</p>
@if(!empty($details))
<div>
    <p>Details:</p>
    @php var_dump($details) @endphp
</div>
@endif
