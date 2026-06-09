<?php

namespace App\Services;

use App\Models\EBadgeLayoutSetting;
use App\Models\UserDetail;
use App\Support\BadgeDisplayText;
use Illuminate\Support\Collection;

class EBadgeLayoutRenderService
{
  private const LINE_HEIGHT = 1.15;
  private const MAX_FONT_REDUCTION_MM = 4.0;
  private const FONT_STEP_MM = 0.2;
  private const MIN_FONT_MM = 7.0;
  private const ELEMENT_GAP_MM = 0.5;
  private const HORIZONTAL_PADDING_MM = 2.0;

  /**
   * @param  Collection<int, EBadgeLayoutSetting>  $layoutSettings
   * @return array<int, array<string, mixed>>
   */
  public function buildRenderedElements(
    UserDetail $user,
    Collection $layoutSettings,
    float $pageWidthMm,
    float $pageHeightMm
  ): array {
    $elements = [];

    foreach ($layoutSettings->sortBy('sequence')->values() as $layout) {
      $field = $layout->field_name;

      if ($field === 'QRcode') {
        $elements[] = $this->buildQrElement($layout, $pageWidthMm);
        continue;
      }

      $value = $this->resolveFieldValue($user, $layout);
      if ($value === '') {
        continue;
      }

      $elements[] = $this->buildTextElement($layout, $value, $pageWidthMm);
    }

    $this->fitTextElements($elements);
    $this->resolveOverlaps($elements, $pageHeightMm);

    return $elements;
  }

  /**
   * @return array<string, mixed>
   */
  protected function buildQrElement(EBadgeLayoutSetting $layout, float $pageWidthMm): array
  {
    $leftMm = (float) ($layout->margin_left ?? 0);
    $rightMm = (float) ($layout->margin_right ?? 0);
    $qrWidthMm = (float) ($layout->width ?? 20);
    $qrHeightMm = (float) ($layout->height ?? 20);

    return [
      'type' => 'qr',
      'field' => 'QRcode',
      'sequence' => (int) ($layout->sequence ?? 0),
      'top_mm' => (float) ($layout->margin_top ?? 0),
      'left_mm' => $leftMm,
      'right_mm' => $rightMm,
      'zone_width_mm' => max(0, $pageWidthMm - $leftMm - $rightMm),
      'qr_width_mm' => $qrWidthMm,
      'qr_height_mm' => $qrHeightMm,
      'text_align' => $layout->text_align ?? 'left',
      'height_mm' => $qrHeightMm,
      'layout_top_mm' => (float) ($layout->margin_top ?? 0),
    ];
  }

  /**
   * @return array<string, mixed>
   */
  protected function buildTextElement(EBadgeLayoutSetting $layout, string $value, float $pageWidthMm): array
  {
    $leftMm = (float) ($layout->margin_left ?? 0);
    $rightMm = (float) ($layout->margin_right ?? 0);
    $maxWidthMm = max(5, $pageWidthMm - $leftMm - $rightMm);
    $widthMm = (float) ($layout->width ?? 0);
    if ($widthMm <= 0) {
      $widthMm = $maxWidthMm;
    } else {
      $widthMm = min($widthMm, $maxWidthMm);
    }

    $fontSizeMm = (float) ($layout->font_size ?? 3.7);

    return [
      'type' => 'text',
      'field' => $layout->field_name,
      'sequence' => (int) ($layout->sequence ?? 0),
      'top_mm' => (float) ($layout->margin_top ?? 0),
      'left_mm' => $leftMm,
      'width_mm' => $widthMm,
      'font_size_mm' => $fontSizeMm,
      'original_font_size_mm' => $fontSizeMm,
      'text_align' => $layout->text_align ?? 'left',
      'font_family' => $this->normalizeFontFamily($layout->font_family ?? 'Helvetica'),
      'font_weight' => $layout->font_weight ?? 'normal',
      'color' => $layout->color ?? '#000000',
      'value' => $value,
      'lines' => 1,
      'height_mm' => $fontSizeMm * self::LINE_HEIGHT,
      'layout_top_mm' => (float) ($layout->margin_top ?? 0),
    ];
  }

  protected function resolveFieldValue(UserDetail $user, EBadgeLayoutSetting $layout): string
  {
    $field = $layout->field_name;

    if ($field === 'Category') {
      return BadgeDisplayText::format((string) ($user->Category ?? ''));
    }

    if (str_starts_with($field, 'Instruction')) {
      return BadgeDisplayText::format((string) ($layout->static_text_value ?? ''));
    }

    return BadgeDisplayText::format((string) ($user->{$field} ?? ''));
  }

  protected function normalizeFontFamily(?string $font): string
  {
    $supported = ['Helvetica', 'Times-Roman', 'Courier'];

    return in_array($font, $supported, true) ? $font : 'Helvetica';
  }

  /**
   * @param  array<int, array<string, mixed>>  $elements
   */
  protected function fitTextElements(array &$elements): void
  {
    foreach ($elements as &$element) {
      if (($element['type'] ?? '') !== 'text') {
        continue;
      }

      $originalFont = (float) $element['original_font_size_mm'];
      $minFont = max(self::MIN_FONT_MM, $originalFont - self::MAX_FONT_REDUCTION_MM);
      $font = $originalFont;
      $widthMm = $this->effectiveTextWidthMm((float) $element['width_mm']);
      $value = (string) $element['value'];
      $fontWeight = (string) ($element['font_weight'] ?? 'normal');

      while ($font > $minFont && $this->estimateLineCount($value, $font, $widthMm, $fontWeight) > 1) {
        $font = round($font - self::FONT_STEP_MM, 2);
      }

      if ($font < $minFont) {
        $font = $minFont;
      }

      $lines = $this->estimateLineCount($value, $font, $widthMm, $fontWeight);
      $element['font_size_mm'] = $font;
      $element['lines'] = $lines;
      $element['height_mm'] = $font * self::LINE_HEIGHT * $lines
        + max(0, $lines - 1) * 0.35;
    }
    unset($element);
  }

    /**
     * Push elements below any field that extends past its layout slot so nothing overlaps.
     *
     * @param  array<int, array<string, mixed>>  $elements
     */
    protected function resolveOverlaps(array &$elements, float $pageHeightMm): void
    {
        if ($elements === []) {
            return;
        }

        usort($elements, function (array $a, array $b): int {
            $topCompare = ($a['layout_top_mm'] ?? $a['top_mm'] ?? 0) <=> ($b['layout_top_mm'] ?? $b['top_mm'] ?? 0);
            if ($topCompare !== 0) {
                return $topCompare;
            }

            return ($a['sequence'] ?? 0) <=> ($b['sequence'] ?? 0);
        });

        for ($pass = 0; $pass < 16; $pass++) {
            $changed = false;

            for ($i = 0; $i < count($elements) - 1; $i++) {
                $bottomMm = (float) $elements[$i]['top_mm']
                    + (float) ($elements[$i]['height_mm'] ?? 0)
                    + self::ELEMENT_GAP_MM;

                for ($j = $i + 1; $j < count($elements); $j++) {
                    if ((float) $elements[$j]['top_mm'] < $bottomMm) {
                        $shift = $bottomMm - (float) $elements[$j]['top_mm'];
                        for ($k = $j; $k < count($elements); $k++) {
                            $elements[$k]['top_mm'] = (float) $elements[$k]['top_mm'] + $shift;
                            $elements[$k]['shifted'] = true;
                        }
                        $changed = true;
                    }
                }
            }

            if (!$changed) {
                break;
            }
        }

        foreach ($elements as &$element) {
            $heightMm = (float) ($element['height_mm'] ?? 0);
            $maxTop = max(0, $pageHeightMm - $heightMm);
            if ((float) $element['top_mm'] > $maxTop) {
                $element['top_mm'] = $maxTop;
                $element['shifted'] = true;
            }
        }
        unset($element);
    }

  protected function effectiveTextWidthMm(float $widthMm): float
  {
    return max(1, $widthMm - self::HORIZONTAL_PADDING_MM);
  }

  protected function estimateLineCount(
    string $text,
    float $fontSizeMm,
    float $widthMm,
    string $fontWeight = 'normal'
  ): int {
    $text = trim($text);
    if ($text === '') {
      return 1;
    }

    if ($widthMm <= 0 || $fontSizeMm <= 0) {
      return 1;
    }

    // Conservative estimate for DomPDF wrapping (bold / serif text is wider).
    $widthFactor = $fontWeight === 'bold' ? 0.62 : 0.56;
    $avgCharWidthMm = $fontSizeMm * $widthFactor;
    $maxCharsPerLine = max(1, (int) floor($widthMm / $avgCharWidthMm));

    $words = preg_split('/\s+/u', $text) ?: [];
    $lines = 1;
    $currentChars = 0;

    foreach ($words as $word) {
      $wordLen = mb_strlen($word);
      $required = $currentChars === 0 ? $wordLen : $wordLen + 1;

      if ($currentChars > 0 && ($currentChars + $required) > $maxCharsPerLine) {
        $lines++;
        $currentChars = $wordLen;
        continue;
      }

      if ($wordLen > $maxCharsPerLine) {
        $lines += (int) ceil($wordLen / $maxCharsPerLine) - ($currentChars > 0 ? 1 : 0);
        $currentChars = $wordLen % $maxCharsPerLine;
        if ($currentChars === 0) {
          $currentChars = $maxCharsPerLine;
        }
        continue;
      }

      $currentChars += $required;
    }

    return max(1, $lines);
  }
}
