import { Controller } from "@hotwired/stimulus"

console.log('here');

export default class extends Controller {
    static targets = [ "name", "output" ]

    greet() {
        this.outputTarget.textContent =
            `Hello, ${this.nameTarget.value}!`
    }
}
