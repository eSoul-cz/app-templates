<?php

declare(strict_types=1);

namespace Tests\Feature\CmsAi;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Infrastructure\Models\Page;
use Illuminate\Support\Facades\Storage;

final class CmsUploadImageTest extends CmsAiTestCase
{
    public function test_an_image_is_stored_and_its_path_works_in_an_image_block_and_as_og_image(): void
    {
        $data = $this->structured($this->callTool($this->token, 'upload_image', ['filename' => 'hero.png', 'content_base64' => base64_encode($this->pixel())]));

        $this->assertSame('image/png', $data['mime']);
        $this->assertStringStartsWith('images/', $data['path']);
        $this->assertTrue(Storage::disk(PublicMediaStore::MEDIA_DISK)->exists($data['path']));
        $this->assertSame($this->pixel(), Storage::disk(PublicMediaStore::MEDIA_DISK)->get($data['path']));

        $page = $this->structured($this->callTool($this->token, 'create_page', [
            'title' => 'With image',
            'slug' => 'with-image',
            'og_image' => $data['path'],
            'blocks' => [['type' => 'image', 'data' => ['image' => $data['path'], 'alt' => 'A pixel']]],
        ]));

        $this->assertSame($data['path'], Page::query()->findOrFail($page['page_id'])->og_image);
    }

    public function test_a_text_file_disguised_as_a_jpg_is_refused_and_nothing_is_stored(): void
    {
        $response = $this->callTool($this->token, 'upload_image', ['filename' => 'photo.jpg', 'content_base64' => base64_encode('<?php echo "hi";')]);

        $this->assertToolError($response, 'not an image');
        $this->assertSame([], Storage::disk(PublicMediaStore::MEDIA_DISK)->allFiles());
    }

    public function test_svg_and_other_types_are_refused_even_with_an_accepted_name(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>';

        $this->assertToolError($this->callTool($this->token, 'upload_image', ['filename' => 'logo.png', 'content_base64' => base64_encode($svg)]), 'not an image');
        $this->assertToolError($this->callTool($this->token, 'upload_image', ['filename' => 'logo.svg', 'content_base64' => base64_encode($svg)]), 'file name');
        $this->assertToolError($this->callTool($this->token, 'upload_image', ['filename' => 'a.gif', 'content_base64' => base64_encode($this->pixel())]), 'file name');
        $this->assertSame([], Storage::disk(PublicMediaStore::MEDIA_DISK)->allFiles());
    }

    public function test_an_image_over_the_size_limit_is_refused(): void
    {
        $oversize = $this->pixel() . str_repeat("\0", 11 * 1024 * 1024);

        $this->assertToolError($this->callTool($this->token, 'upload_image', ['filename' => 'big.png', 'content_base64' => base64_encode($oversize)]), 'at most');
        $this->assertSame([], Storage::disk(PublicMediaStore::MEDIA_DISK)->allFiles());
    }

    public function test_an_image_over_the_dimension_limit_is_refused(): void
    {
        $header = pack('NN', 9000, 9000) . "\x08\x06\x00\x00\x00";
        $png = "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $header . pack('N', crc32('IHDR' . $header));

        $this->assertToolError($this->callTool($this->token, 'upload_image', ['filename' => 'wide.png', 'content_base64' => base64_encode($png)]), 'px');
        $this->assertSame([], Storage::disk(PublicMediaStore::MEDIA_DISK)->allFiles());
    }

    public function test_content_that_is_not_base64_is_refused(): void
    {
        $this->assertToolError($this->callTool($this->token, 'upload_image', ['filename' => 'a.png', 'content_base64' => 'not base64 !!']), 'base64');
    }
}
