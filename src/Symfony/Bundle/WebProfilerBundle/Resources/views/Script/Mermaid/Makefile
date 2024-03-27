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

.PHONY: mermaid-flowchart-v2.min.js
mermaid-flowchart-v2.min.js: | mermaid-$(if $(tag),$(tag),$(error missing “tag” variable))/node_modules
	$(file >mermaid-$(tag)/packages/mermaid/src/diagram-api/diagram-orchestration.ts,$(diagram-orchestration))
	pnpm -C mermaid-$(tag) run build
	cp mermaid-$(tag)/packages/mermaid/dist/mermaid.min.js $@

mermaid-%/node_modules: | mermaid-%
	pnpm -C $(@D) install --ignore-scripts

mermaid-%:
	curl -fL https://github.com/mermaid-js/mermaid/archive/refs/tags/$*.tar.gz | tar -xz --strip-components=1 --one-top-level=$@

