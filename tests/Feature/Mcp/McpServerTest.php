<?php

use App\Mcp\Servers\LaporankuServer;
use App\Mcp\Tools\CreateDocument;
use App\Mcp\Tools\ExportDocument;
use App\Mcp\Tools\GetDocument;
use App\Mcp\Tools\ListTemplates;
use App\Mcp\Tools\UpdateChapter;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;

function mcpDocument(User $owner, array $overrides = []): Document
{
    return Document::factory()->for($owner)->create($overrides + [
        'title' => 'Pengaruh Media Sosial',
        'content' => json_decode(file_get_contents(base_path('tests/Fixtures/documents/makalah.json')), true),
    ]);
}

test('the endpoint rejects requests without a token', function () {
    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
});

test('a personal access token reaches tools/list over HTTP', function () {
    $user = User::factory()->create();
    $token = $user->createToken('tes')->plainTextToken;

    $response = $this->withToken($token)->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

    $response->assertOk();
    expect(collect($response->json('result.tools'))->pluck('name')->all())
        ->toContain('list-templates', 'create-document', 'get-document', 'update-chapter', 'export-document');
});

test('guest accounts cannot use the MCP endpoint', function () {
    $guest = User::factory()->guest()->create();
    Sanctum::actingAs($guest);

    $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertForbidden();
});

test('list_templates returns the default template, types and levels', function () {
    LaporankuServer::actingAs(User::factory()->create())
        ->tool(ListTemplates::class)
        ->assertOk()
        ->assertSee(['default', 'Times New Roman', 'laporan_pkl', 'smk']);
});

test('create_document saves a valid draft owned by the caller', function () {
    $user = User::factory()->create();

    LaporankuServer::actingAs($user)
        ->tool(CreateDocument::class, ['title' => 'Makalah Baru', 'type' => 'makalah', 'education_level' => 'sma'])
        ->assertOk()
        ->assertSee('Makalah Baru');

    $document = Document::where('user_id', $user->id)->sole();
    expect($document->education_level->value)->toBe('sma')->and($document->content['cover']['title'])->toBe('Makalah Baru');
});

test('create_document rejects invalid input and invalid chapters without saving', function () {
    $user = User::factory()->create();
    $server = LaporankuServer::actingAs($user);

    $server->tool(CreateDocument::class, ['title' => 'X', 'type' => 'bukan-jenis'])->assertHasErrors();

    $server->tool(CreateDocument::class, [
        'title' => 'X', 'type' => 'makalah',
        'chapters' => [['id' => (string) str()->uuid(), 'title' => 'BAB I Pendahuluan', 'sections' => []]],
    ])->assertHasErrors();

    expect(Document::count())->toBe(0);
});

test('create_document is refused once the free monthly quota is used', function () {
    $user = User::factory()->create();
    Document::factory()->for($user)->count(config('plans.free_documents_per_month'))->create();

    LaporankuServer::actingAs($user)
        ->tool(CreateDocument::class, ['title' => 'Satu Lagi', 'type' => 'makalah'])
        ->assertHasErrors(['Kuota dokumen']);
});

test('a pro user is not limited by the free quota', function () {
    $user = User::factory()->pro()->create();
    Document::factory()->for($user)->count(config('plans.free_documents_per_month'))->create();

    LaporankuServer::actingAs($user)
        ->tool(CreateDocument::class, ['title' => 'Satu Lagi', 'type' => 'makalah'])
        ->assertOk();
});

test('get_document returns the owner document and hides foreign ones', function () {
    $owner = User::factory()->create();
    $document = mcpDocument($owner);

    LaporankuServer::actingAs($owner)->tool(GetDocument::class, ['id' => $document->id])->assertOk()->assertSee('Pengaruh Media Sosial');
    LaporankuServer::actingAs(User::factory()->create())->tool(GetDocument::class, ['id' => $document->id])->assertHasErrors(['tidak ditemukan']);
});

test('update_chapter replaces a chapter and syncs nothing else', function () {
    $owner = User::factory()->create();
    $document = mcpDocument($owner);
    $chapterId = $document->content['chapters'][0]['id'];

    LaporankuServer::actingAs($owner)->tool(UpdateChapter::class, [
        'id' => $document->id,
        'chapter_id' => $chapterId,
        'chapter' => ['title' => 'Pendahuluan Baru', 'sections' => []],
    ])->assertOk();

    $fresh = $document->fresh();
    expect($fresh->content['chapters'][0]['title'])->toBe('Pendahuluan Baru')
        ->and($fresh->content['chapters'][0]['id'])->toBe($chapterId)
        ->and(count($fresh->content['chapters']))->toBe(count($document->content['chapters']));
});

test('update_chapter with invalid content saves nothing', function () {
    $owner = User::factory()->create();
    $document = mcpDocument($owner);
    $before = $document->content;

    LaporankuServer::actingAs($owner)->tool(UpdateChapter::class, [
        'id' => $document->id,
        'chapter_id' => $before['chapters'][0]['id'],
        'chapter' => ['title' => '1. Pendahuluan', 'sections' => []],
    ])->assertHasErrors();

    expect($document->fresh()->content)->toBe($before);
});

test('update_chapter cannot touch another user document or an unknown chapter', function () {
    $owner = User::factory()->create();
    $document = mcpDocument($owner);
    $chapterId = $document->content['chapters'][0]['id'];

    LaporankuServer::actingAs(User::factory()->create())->tool(UpdateChapter::class, [
        'id' => $document->id, 'chapter_id' => $chapterId, 'chapter' => ['title' => 'Diretas', 'sections' => []],
    ])->assertHasErrors(['tidak ditemukan']);

    LaporankuServer::actingAs($owner)->tool(UpdateChapter::class, [
        'id' => $document->id, 'chapter_id' => (string) str()->uuid(), 'chapter' => ['title' => 'Baru', 'sections' => []],
    ])->assertHasErrors(['Bab dengan id']);

    expect($document->fresh()->content['chapters'][0]['title'])->not->toBe('Diretas');
});

test('export_document returns a signed link over HTTP that downloads the DOCX', function () {
    $owner = User::factory()->create();
    $document = mcpDocument($owner);

    $rpc = $this->withToken($owner->createToken('tes')->plainTextToken)->postJson('/mcp', [
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
        'params' => ['name' => 'export-document', 'arguments' => ['id' => $document->id, 'format' => 'docx']],
    ])->assertOk();

    $url = json_decode($rpc->json('result.content.0.text'), true)['url'];
    expect($url)->toContain('signature=');

    $response = $this->get($url); // no session: the signature is the credential
    $response->assertOk();
    expect(file_get_contents($response->baseResponse->getFile()->getPathname(), length: 2))->toBe('PK');
    $response->streamedContent();
});

test('export_document refuses PDF on the free plan when it is Pro only', function () {
    config(['plans.pdf_pro_only' => true]);
    $owner = User::factory()->create();
    $document = mcpDocument($owner);

    LaporankuServer::actingAs($owner)->tool(ExportDocument::class, ['id' => $document->id, 'format' => 'pdf'])->assertHasErrors(['paket Pro']);
    LaporankuServer::actingAs(User::factory()->pro()->create())->tool(ExportDocument::class, ['id' => $document->id, 'format' => 'pdf'])->assertHasErrors(['tidak ditemukan']);
});

test('export_document refuses a document without chapters', function () {
    $owner = User::factory()->create();
    $document = mcpDocument($owner, ['content' => ['chapters' => []]]);

    LaporankuServer::actingAs($owner)->tool(ExportDocument::class, ['id' => $document->id, 'format' => 'docx'])->assertHasErrors(['belum punya isi']);
});

function mcpLink(Document $document, User $user, string $format = 'docx', ?DateTimeInterface $until = null): string
{
    return URL::temporarySignedRoute('mcp.download', $until ?? now()->addMinutes(10), ['document' => $document->id, 'user' => $user->id, 'format' => $format]);
}

test('the MCP download link works without a session and without a login redirect', function () {
    $owner = User::factory()->create();

    $response = $this->get(mcpLink(mcpDocument($owner), $owner))->assertOk();

    expect(file_get_contents($response->baseResponse->getFile()->getPathname(), length: 2))->toBe('PK');
    $response->streamedContent();
});

test('a tampered MCP download link is rejected', function () {
    $owner = User::factory()->create();
    $document = mcpDocument($owner);
    $url = mcpLink($document, $owner);

    $this->get($url.'x')->assertForbidden();
    $this->get(str_replace('user='.$owner->id, 'user='.User::factory()->create()->id, $url))->assertForbidden();
    $this->get(str_replace('format=docx', 'format=pdf', $url))->assertForbidden();
    $this->get(route('mcp.download', ['document' => $document->id, 'user' => $owner->id]))->assertForbidden();
});

test('an expired MCP download link is rejected', function () {
    $owner = User::factory()->create();

    $this->get(mcpLink(mcpDocument($owner), $owner, 'docx', now()->subMinute()))->assertForbidden();
});

test('a validly signed link for someone else document is still refused', function () {
    $document = mcpDocument(User::factory()->create());
    $intruder = User::factory()->create();

    $this->get(mcpLink($document, $intruder))->assertForbidden();
});

test('MCP PDF download follows the plan: Free refused, Pro gets the PDF', function () {
    config(['plans.pdf_pro_only' => true]);
    Http::fake(['*' => Http::response('%PDF-1.7 fake')]);

    $free = User::factory()->create();
    $this->get(mcpLink(mcpDocument($free), $free, 'pdf'))->assertForbidden();

    $pro = User::factory()->pro()->create();
    $response = $this->get(mcpLink(mcpDocument($pro), $pro, 'pdf'))->assertOk();
    expect(file_get_contents($response->baseResponse->getFile()->getPathname(), length: 4))->toBe('%PDF');
    $response->streamedContent();
});

test('MCP download is refused for guest accounts', function () {
    $guest = User::factory()->guest()->create();

    $this->get(mcpLink(mcpDocument($guest), $guest))->assertForbidden();
});
