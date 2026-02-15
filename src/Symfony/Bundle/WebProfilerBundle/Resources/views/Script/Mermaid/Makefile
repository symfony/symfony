define diagram-orchestration
import flowchartV2 from '../diagrams/flowchart/flowDetector-v2.js';
import { registerDiagram } from './diagramAPI.js';

let hasLoadedDiagrams = false;
export const addDiagrams = async () => {
  if (hasLoadedDiagrams) {
    return;
  }
  hasLoadedDiagrams = true;
  const {id, diagram} = await flowchartV2.loader();
  registerDiagram(id, diagram, () => true);
};
endef

override tag := 11.12.0

.PHONY: mermaid-flowchart-v2.min.js
mermaid-flowchart-v2.min.js: | repo-$(tag)/node_modules
	$(file >repo-$(tag)/packages/mermaid/src/diagram-api/diagram-orchestration.ts,$(diagram-orchestration))
	pnpm -C repo-$(tag) run build:mermaid
	cp repo-$(tag)/packages/tiny/dist/mermaid.tiny.js $@

repo-$(tag)/node_modules: | repo-$(tag)
	pnpm -C $(@D) install --ignore-scripts

.SECONDARY: repo-$(tag)
repo-$(tag):
	curl -fL https://github.com/mermaid-js/mermaid/archive/refs/tags/mermaid@$(tag).tar.gz | tar -xz --strip-components=1 --one-top-level=$@

.PHONY: clean
clean:
	rm -rf ./repo-*
