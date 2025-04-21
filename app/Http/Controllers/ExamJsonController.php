<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\Element\Image;
use PhpOffice\PhpWord\IOFactory;

class ExamJsonController extends Controller
{

    public function debugDocx(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:docx'
        ]);

        $file = $request->file('document');
        $phpWord = IOFactory::load($file->getRealPath());

        $output = [];

        foreach ($phpWord->getSections() as $sectionIndex => $section) {
            foreach ($section->getElements() as $elementIndex => $element) {
                $elementType = get_class($element);

                if (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $childIndex => $child) {
                        $childType = get_class($child);
                        if (method_exists($child, 'getText')) {
                            $text = $child->getText();
                            $output[] = "[Sec $sectionIndex] $childType: " . $text;
                        } elseif ($child instanceof \PhpOffice\PhpWord\Element\Image) {
                            $output[] = "[Sec $sectionIndex] IMAGE: " . $child->getSource();
                        } else {
                            $output[] = "[Sec $sectionIndex] $childType: (no text)";
                        }
                    }
                } elseif (method_exists($element, 'getText')) {
                    $output[] = "[Sec $sectionIndex] $elementType: " . $element->getText();
                } else {
                    $output[] = "[Sec $sectionIndex] $elementType (no method)";
                }
            }
        }


        return response()->json($output);
    }

    public function convertEssayOnly(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:docx'
        ]);

        $file = $request->file('document');
        $phpWord = IOFactory::load($file->getRealPath());

        $imageCounter = 1;
        $imageDir = storage_path('app/public/document_images/');
        if (!file_exists($imageDir)) mkdir($imageDir, 0777, true);

        $htmlBlocks = [];
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (!method_exists($element, 'getElements')) continue;

                $blockHtml = '';
                foreach ($element->getElements() as $child) {
                    if ($child instanceof \PhpOffice\PhpWord\Element\Text) {
                        $text = htmlspecialchars($child->getText());
                        $style = $child->getFontStyle();

                        $styles = [];
                        if ($style && $style->isBold()) $styles[] = 'font-weight:bold';
                        if ($style && $style->getColor()) $styles[] = 'color:#' . $style->getColor();

                        $blockHtml .= !empty($styles)
                            ? '<span style="' . implode(';', $styles) . '">' . $text . '</span>'
                            : $text;
                    } elseif ($child instanceof \PhpOffice\PhpWord\Element\TextBreak) {
                        $blockHtml .= '<br>';
                    } elseif ($child instanceof \PhpOffice\PhpWord\Element\Image) {
                        $ext = pathinfo($child->getSource(), PATHINFO_EXTENSION);
                        $imgName = 'img_' . $imageCounter++ . '.' . $ext;
                        $imgPath = $imageDir . $imgName;
                        copy($child->getSource(), $imgPath);
                        $imgUrl = asset('storage/document_images/' . $imgName);
                        $blockHtml .= '[[IMAGE:' . $imgUrl . ']]';
                    }
                }

                if (!empty(trim(strip_tags($blockHtml)))) {
                    $htmlBlocks[] = $blockHtml;
                }
            }
        }

        $fullHtml = implode('<br>', $htmlBlocks);
        $rawBlocks = preg_split('/Jawaban\s*Benar\s*[:\-]?\s*/i', $fullHtml);

        $questions = [];

        for ($i = 0; $i < count($rawBlocks) - 1; $i++) {
            $questionBlock = trim($rawBlocks[$i]);
            $answerRaw = trim(strip_tags($rawBlocks[$i + 1]));

            $lines = array_values(array_filter(array_map('trim', explode('<br>', $questionBlock))));
            $questionText = array_shift($lines);
            $questionImage = null;

            if (preg_match('/\[\[IMAGE:(.*?)\]\]/', $questionText, $m)) {
                $questionImage = $m[1];
                $questionText = str_replace($m[0], '', $questionText);
            }

            $questions[] = [
                'id' => 'q' . str_pad(count($questions) + 1, 3, '0', STR_PAD_LEFT),
                'type' => 'essay',
                'category' => null,
                'question' => strip_tags($questionText),
                'questionImage' => $questionImage,
                'answer' => [$answerRaw]
            ];
        }

        return [
            'questions' => $questions
        ];
    }

    //
    public function convertToJson(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:txt,,doc,docx'
        ]);

        $file = $request->file('document');
        $extension = $file->getClientOriginalExtension();

        if ($extension === 'txt') {
            return $this->processTxtFile($file);
        } elseif ($extension === 'docx') {
            return response()->json($this->processDocxFile($file));
        }

        return response()->json(['error' => 'Unsupported file type'], 400);
    }

    private function processDocxFile($file)
    {
        $phpWord = IOFactory::load($file->getRealPath());

        $imageCounter = 1;
        $imageDir = storage_path('app/public/document_images/');
        if (!file_exists($imageDir)) mkdir($imageDir, 0777, true);

        $htmlBlocks = [];
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (!method_exists($element, 'getElements')) continue;

                $blockHtml = '';
                foreach ($element->getElements() as $child) {
                    if ($child instanceof \PhpOffice\PhpWord\Element\Text) {
                        $text = htmlspecialchars($child->getText());
                        $style = $child->getFontStyle();

                        $styles = [];
                        if ($style && $style->isBold()) $styles[] = 'font-weight:bold';
                        if ($style && $style->getColor()) $styles[] = 'color:#' . $style->getColor();

                        $blockHtml .= !empty($styles)
                            ? '<span style="' . implode(';', $styles) . '">' . $text . '</span>'
                            : $text;
                    } elseif ($child instanceof \PhpOffice\PhpWord\Element\TextBreak) {
                        $blockHtml .= '<br>';
                    } elseif ($child instanceof \PhpOffice\PhpWord\Element\Image) {
                        $ext = pathinfo($child->getSource(), PATHINFO_EXTENSION);
                        $imgName = 'img_' . $imageCounter++ . '.' . $ext;
                        $imgPath = $imageDir . $imgName;
                        copy($child->getSource(), $imgPath);
                        $imgUrl = asset('storage/document_images/' . $imgName);
                        $blockHtml .= '[[IMAGE:' . $imgUrl . ']]';
                    }
                }

                if (!empty(trim(strip_tags($blockHtml)))) {
                    $htmlBlocks[] = $blockHtml;
                }
            }
        }

        $fullHtml = implode('<br>', $htmlBlocks);
        $rawBlocks = preg_split('/Jawaban\s*Benar\s*[:\-]?\s*/i', $fullHtml);

        $questions = [];

        for ($i = 0; $i < count($rawBlocks) - 1; $i++) {
            $questionBlock = trim($rawBlocks[$i]);
            $answerRaw = trim(strip_tags($rawBlocks[$i + 1]));

            $lines = array_values(array_filter(array_map('trim', explode('<br>', $questionBlock))));
            $questionText = array_shift($lines);
            $questionImage = null;

            // Check if image is embedded in question
            if (preg_match('/\[\[IMAGE:(.*?)\]\]/', $questionText, $m)) {
                $questionImage = $m[1];
                $questionText = '<p>' . trim(str_replace($m[0], '', $questionText)) . '</p>';
            } else {
                $questionText = '<p>' . trim($questionText) . '</p>';
            }

            $labels = ['A', 'B', 'C', 'D', 'E'];
            $options = [];

            $imageOnlyLines = array_filter($lines, fn($line) => str_contains($line, '[[IMAGE:'));
            $textOnlyLines = array_filter($lines, fn($line) => !str_contains($line, '[[IMAGE:'));

            if (count($imageOnlyLines) >= 2 && count($imageOnlyLines) <= 5 && count($textOnlyLines) === 0) {
                // All lines are images = option images
                foreach ($imageOnlyLines as $idx => $imgLine) {
                    $label = $labels[$idx] ?? chr(65 + $idx);
                    preg_match('/\[\[IMAGE:(.*?)\]\]/', $imgLine, $imgMatch);
                    $options[$label] = [
                        'label' => $label,
                        'text' => null,
                        'image' => $imgMatch[1] ?? null,
                        'explanation' => null,
                        'correct' => strtoupper($label) === strtoupper($answerRaw)
                    ];
                }
            } else {
                // Mixed or text options
                foreach ($lines as $idx => $line) {
                    $label = $labels[$idx] ?? chr(65 + $idx);
                    $img = null;
                    if (preg_match('/\[\[IMAGE:(.*?)\]\]/', $line, $m)) {
                        $img = $m[1];
                        $line = str_replace($m[0], '', $line);
                    }

                    $options[$label] = [
                        'label' => $label,
                        'text' => trim($line) ? '<p>' . trim($line) . '</p>' : null,
                        'image' => $img,
                        'explanation' => null,
                        'correct' => strtoupper($label) === strtoupper($answerRaw)
                    ];
                }
            }

            $questions[] = [
                'id' => 'q' . str_pad(count($questions) + 1, 3, '0', STR_PAD_LEFT),
                'type' => 'multiple-choice',
                'category' => null,
                'question' => $questionText,
                'questionImage' => $questionImage,
                'options' => array_values($options)
            ];
        }

        return [
            'questions' => $questions
        ];
    }
}
