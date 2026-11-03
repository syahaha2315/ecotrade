import pathlib
import re

css = (pathlib.Path(__file__).resolve().parent.parent / 'public/assets/app.css').read_text()
palettes = re.findall(r'(?:\:root|html\[data-theme="dark"\])\s*\{(.*?)\}', css, re.S)


def luminance(colour):
    channels = [int(colour[index:index + 2], 16) / 255 for index in (1, 3, 5)]
    return sum(weight * (value / 12.92 if value <= .04045 else ((value + .055) / 1.055) ** 2.4)
               for weight, value in zip((.2126, .7152, .0722), channels))


pairs = [(text, background, 4.5) for text in ('ink', 'muted', 'green', 'error')
         for background in ('paper', 'surface', 'wash')]
pairs += [('on-green', 'green', 4.5), ('on-green', 'green-hover', 4.5)]
pairs += [(text, background, 3) for text in ('line', 'focus')
          for background in ('paper', 'surface', 'wash')]
assert len(palettes) == 2
for name, palette in zip(('light', 'dark'), palettes):
    colours = dict(re.findall(r'--([a-z-]+):\s*(#[0-9a-fA-F]{6})', palette))
    for foreground, background, threshold in pairs:
        low, high = sorted((luminance(colours[foreground]), luminance(colours[background])))
        ratio = (high + .05) / (low + .05)
        assert ratio >= threshold, (name, foreground, background, ratio)
    print(f'PASS: {name}, {len(pairs)} text, boundary and focus pairings.')
print(f'{len(pairs) * len(palettes)} contrast pairings passed.')
