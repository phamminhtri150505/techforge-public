<a @class(['active' => request()->routeIs('home', 'articles.*')]) @if(request()->routeIs('home', 'articles.*')) aria-current="page" @endif href="{{ route('home') }}">Tin công nghệ</a>
<a @class(['active' => request()->routeIs('products.*')]) @if(request()->routeIs('products.*')) aria-current="page" @endif href="{{ route('products.index') }}">Linh kiện</a>
<a @class(['nav-builder-entry', 'active' => request()->routeIs('builder.*', 'reference-builds.*')]) @if(request()->routeIs('builder.*', 'reference-builds.*')) aria-current="page" @endif href="{{ route('builder.index') }}">PC Builder</a>
<a @class(['active' => request()->routeIs('cart.*')]) @if(request()->routeIs('cart.*')) aria-current="page" @endif href="{{ route('cart.index') }}">Giỏ hàng</a>
