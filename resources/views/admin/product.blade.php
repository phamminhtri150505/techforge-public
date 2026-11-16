@extends('layouts.app')
@section('title', $product->exists ? 'Sửa linh kiện' : 'Thêm linh kiện')
@section('content')
    @include('admin.product-create-fields')
@endsection
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/product-create.css') }}?v=20261106">
@endpush
@push('scripts')
    <script src="{{ asset('js/product-create.js') }}?v=20261107" defer></script>
@endpush
