import { Application } from "@hotwired/stimulus"
import HelloController from "./controllers/hello_controller.js"

const application = Application.start()

application.debug = false
window.Stimulus   = application

Stimulus.register("hello", HelloController)

export { application }
