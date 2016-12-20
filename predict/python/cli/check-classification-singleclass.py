import sys
import json

# From scratch - neural network.
from BinaryClassifierNN import BinaryClassifierNN
binary_classifier = BinaryClassifierNN()

# Sklearn binary classifier - logistic regression.
#from BinaryClassifier import BinaryClassifier
#binary_classifier = BinaryClassifier()

# TensorFlow binary classifier - NN.
#from BinaryClassifierTensorFlow import BinaryClassifierTensorFlow
#binary_classifier = BinaryClassifierTensorFlow()

# TensorFlow binary classifier - logistic regression.
#from BinaryClassifierSkflow import BinaryClassifierSkflow
#binary_classifier = BinaryClassifierSkflow()

# TensorFlow binary classifier - deep neural network.
#from BinaryClassifierDNN import BinaryClassifierDNN
#binary_classifier = BinaryClassifierDNN()

# Missing arguments.
if len(sys.argv) < 5:
    result = dict()
    result['id'] = int(binary_classifier.get_id())
    result['exitcode'] = 1
    result['errors'] = ['Missing arguments, you should set: \
The file, the minimum phi value to consider the model as valid, \
the minimum deviation to accept the model as valid, \
the number of times the evaluation will run. Received: ' + ' '.join(sys.argv)]
    print(json.dumps(result))
    sys.exit(result['exitcode'])

result = binary_classifier.evaluate(sys.argv[1], float(sys.argv[2]),
    float(sys.argv[3]), int(sys.argv[4]))

# If we consider the classification as valid we store coeficients and intercepts.
#if result['exitcode'] == 0:
    #binary_classifier.store_model()

print(json.dumps(result))
sys.exit(result['exitcode'])
