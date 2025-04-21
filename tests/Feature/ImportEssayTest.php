<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\postJson;

function uploadTestFile(string $fileName): UploadedFile
{
    $path = storage_path("app/public/testing/{$fileName}");

    expect(file_exists($path))->toBeTrue("File {$fileName} not found at expected location");

    return new UploadedFile(
        $path,
        $fileName,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true
    );
}


it('returns correct structure and data for essay with no image', function () {
    $file = uploadTestFile('essay_no_image.docx');

    $response = postJson('/api/import-essay', ['document' => $file]);

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'questions');

    $question = $response->json('questions.0');

    expect($question)->toHaveKeys(['id', 'type', 'question', 'questionImage', 'answer'])
        ->and($question['type'])->toBe('essay')
        ->and($question['questionImage'])->toBeNull()
        ->and($question['answer'])->toBeArray()
        ->and($question['answer'][0])->toContain('nutrisi');
});

it('detects image before question as questionImage', function () {
    $file = uploadTestFile('essay_image_before.docx');

    $response = postJson('/api/import-essay', ['document' => $file]);

    $response->assertStatus(200);
    $question = $response->json('questions.0');

    expect($question['type'])->toBe('essay')
        ->and($question['questionImage'])->toStartWith('http')
        ->and($question['question'])->toContain('?');
});

it('detects image after question as questionImage', function () {
    $file = uploadTestFile('essay_image_after.docx');

    $response = postJson('/api/import-essay', ['document' => $file]);

    $response->assertStatus(200);
    $question = $response->json('questions.0');

    expect($question['type'])->toBe('essay')
        ->and($question['questionImage'])->toStartWith('http')
        ->and($question['question'])->toContain('?');
});

it('returns valid structure for default essay file', function () {
    $file = uploadTestFile('essay_valid.docx');

    $response = postJson('/api/import-essay', ['document' => $file]);

    $response->assertStatus(200);
    $response->assertJsonCount(1, 'questions');

    $question = $response->json('questions.0');

    expect($question['type'])->toBe('essay')
        ->and($question['question'])->toContain('fungsi kantong')
        ->and($question['answer'][0])->toContain('nutrisi');
});
