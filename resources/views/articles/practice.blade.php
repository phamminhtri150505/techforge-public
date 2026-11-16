<section class="article-practice" id="article-practice" aria-labelledby="practice-title">
    <p class="eyebrow">Từ kiến thức đến lựa chọn</p>
    <h2 id="practice-title">Thực hành trong PC Builder</h2>
    <p>Đây là mục tiêu được biên tập cho bài viết, chưa phải nhu cầu bạn đã chọn. Chuyển sang Builder để điều chỉnh và đối chiếu với linh kiện của bạn.</p>
    @include('articles.requirement-values', ['values' => $requirements])
    <form method="post" action="{{ route('articles.builder', $article->slug) }}">
        @csrf
        <input type="hidden" name="transfer_token" value="{{ $transferToken }}">
        @if($needsConfirmation)
            <div class="article-requirements-current">
                <h3>Nhu cầu đang dùng trong Builder</h3>
                @include('articles.requirement-values', ['values' => $currentRequirements])
                <label class="article-replace-confirm"><input type="checkbox" name="confirm_replace" value="1" required aria-describedby="transfer-effect"> Tôi đồng ý thay nhu cầu đang dùng bằng mục tiêu trong bài viết.</label>
            </div>
        @endif
        <p class="article-transfer-note" id="transfer-effect">Chỉ cập nhật nhu cầu. Giữ linh kiện đã chọn và dấu “Tôi đã có”; không tự thêm sản phẩm hoặc thay giỏ hàng.</p>
        @error('requirements_transfer')<p class="article-transfer-error" role="alert">{{ $message }}</p>@enderror
        <button class="btn btn-forge">Chuyển yêu cầu sang Builder →</button>
    </form>
</section>
