# Legal Block Generator
This module provides a configurable block that can serve as a footer.

## Configuration
The block can be configured when adding it to the desired layout region.
The configuration is grouped into different templates, which conist of:
- `links`: These include links that are shown on the block.
- `logos`: These are the logos that are shown on the block.

### Custom Templates
The module already comes with some default configurations, but you can also define a template set that better fits your needs.
To do so, just create a `templates.yaml` file in `public://dis_legal_block` (`public://` usually translates to `/sites/default/files/`) and stick to the template defined in this modules' `/config/isntall/dis_legal_block.templates.yml`.

The logos you use in the `logos` section should be located in the corresponding directory in `public://dis_legal_block`.

Take this `templates.yaml` as an example:
```yml
logos:
  ReturnToMonkey:
    - image: monkey1.png
      alt: Monkey
      url: "https://monkey.com"
      height: 185
    - image: monkey2.png
      alt: Monkey
      url: "https://monkey.com"
      height: 185
    - image: monkey3.png
      alt: Monkey
      url: "https://monkey.com"
      height: 185
```
Note: Logos that are first in the `yaml` als come first in the block.

Then your file tree should look like this:
```
dis_legal_block
    ├── ReturnToMonkey
    │   ├── monkey1.png
    │   ├── monkey2.png
    │   └── monkey3.png
    └── templates.yaml
```
If you wish to reuse shipped logos like the CDI logo, just copy them to a custom template folder and use them accordingly.

### Remarks:
The block HTML is generated on block save. So in case the module gets updated the routine would be:
1. Update the code via `git` or `composer` if available.
2. `drush cr` since that rebuilds the Twig caches to use the updated template.
3. Save the block to rebuild the HTML using the new Twig template.

Generally: In case the block is empty after saving just `drush cr` and save the block again.