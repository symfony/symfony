define diagram-orchestration
import { diagram as flowchartV2 } from '../diagrams/flowchart/flowDiagram-v2.js';
import { registerDiagram } from './diagramAPI.js';

let hasLoadedDiagrams = false;
export const addDiagrams = () => {
  if (hasLoadedDiagrams) {
    return;
  }
  hasLoadedDiagrams = true;
  registerDiagram('flowchart-v2', flowchartV2, () => true);
};
endef

override tag := v10.9.0

.PHONY: mermaid-flowchart-v2.min.js
mermaid-flowchart-v2.min.js: | repo-$(tag)/node_modules
	$(file >repo-$(tag)/packages/mermaid/src/diagram-api/diagram-orchestration.ts,$(diagram-orchestration))
	pnpm -C repo-$(tag) run build
	cp repo-$(tag)/packages/mermaid/dist/mermaid.min.js $@

repo-$(tag)/node_modules: | repo-$(tag)
	pnpm -C $(@D) install --ignore-scripts

.SECONDARY: repo-$(tag)
repo-$(tag):
	curl -fL https://github.com/mermaid-js/mermaid/archive/refs/tags/$(tag).tar.gz | tar -xz --strip-components=1 --one-top-level=$@

.PHONY: clean
clean:
	rm -rf ./repo-*
