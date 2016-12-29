import sys
import random

import numpy as np

class NN():


    def __init__(self, nn_iterations, epsilon, reg_lambda, nn_hidden, debug=False):

        self.nn_iterations = nn_iterations
        self.epsilon = epsilon
        self.reg_lambda = reg_lambda
        self.nn_hidden = nn_hidden
        self.debug = debug

        self.Ws = []
        self.bs = []

        # Input + hidden + output.
        self.num_layers = 1 + len(self.nn_hidden) + 1

    def initialise_model(self, n_input_dimensions):

        # Initialise neurons weights and biases.
        # We have the input layer one + 1 layer for each hidden layer.
        weights_sizes = [None] * (1 + len(self.nn_hidden))

        weights_sizes[0] = {}
        weights_sizes[0]['in'] = n_input_dimensions

        for i, hidden_layer_dimensions in enumerate(self.nn_hidden):
            weights_sizes[i]['out'] = hidden_layer_dimensions

            # Next layer (the last one will be completed by the output number of units.)
            weights_sizes[i + 1] = {}
            weights_sizes[i + 1]['in'] = hidden_layer_dimensions

        # 2 output units in binary classification.
        weights_sizes[2]['out'] = 2

        # Random weights and biases initialisation.
        np.random.seed(0)

        for weight_size in weights_sizes:
            self.Ws.append(np.random.randn(weight_size['in'], weight_size['out']) / np.sqrt(weight_size['in']))
            self.bs.append(np.zeros((1, weight_size['out'])))

    def fit(self, X, y):

        # We can do this in __init__ because we need to know the number of
        # input layers.
        self.initialise_model(len(X[0]))

        for it in range(0, self.nn_iterations):
            probs, zs, as_ = self.forward_prop(X)

            dWs, dbs = self.back_prop(probs, y, zs, as_)

            # Output layer does not have weights so only until self.num_layers - 1.
            for i in range(0, self.num_layers - 1):
                self.Ws[i] += - self.epsilon * dWs[i]
                self.bs[i] += - self.epsilon * dbs[i]

            if self.debug == True and it % 1000 == 0:
                # We are using the training set, not a reliable measure, just to see
                # if the neural network learning rate is good.
                print("Iteration %i: training loss = %f" % (
                    it, self.calculate_loss(self.Ws, self.bs, X, y))
                )


    def predict(self, x):
        probs, _, _ = self.forward_prop(x)
        return np.argmax(probs, axis=1)


    def forward_prop(self, x):

        zs = []
        as_ = []

        # Fill the input layer ones with x and leave zs[0] empty
        zs.append(None)
        as_.append(x)

        # Propagate from the first hidden layer until the output layer.
        for i in range(1, self.num_layers):

            # Using:
            # - Previous layer activations (input layer values when 'i' = 0).
            # - Weights between previous layer and this one (indexed in the previous layer)
            # - Biases between previous layer and this one (indexed in the previous layer)
            zs.append(as_[i - 1].dot(self.Ws[i - 1]) + self.bs[i - 1])
            as_.append(np.tanh(zs[i]))

        # zs[self.num_layers] is the output layer.
        try:
            # overflow encountered in exp.
            exp_scores = np.exp(zs[self.num_layers - 1])
        except FloatingPointError:
            # All to -1.
            exp_scores = np.ones(zs[self.num_layers - 1].shape) * -1

        try:
            probs = exp_scores / np.sum(exp_scores, axis=1, keepdims=True)
        except FloatingPointError:
            # invalid value encountered in divide.
            # All to -1.
            exp_scores = np.ones(zs[self.num_layers - 1].shape) * -1
            probs = exp_scores / np.sum(exp_scores, axis=1, keepdims=True)

        return probs, zs, as_


    def back_prop(self, probs, y, zs, as_):

        # Initialise to empty.
        deltas = [None] * self.num_layers

        n_examples = len(y)

        # The output layer delta is the activation probabilities minus y.
        # If instead of a [0,1,0,1,1...] vector we would have a matrix
        # [[1,0],[1,0],[0,1],...] we would just probs - y.
        deltas[self.num_layers - 1] = probs
        deltas[self.num_layers - 1][range(n_examples), y] -= 1

        # Calculate deltas from the last hidden layer to the first hidden layer.
        # Using -2 instead of -1 because we already have the output layer error.
        for i in range(self.num_layers - 2, 0, -1):

            try:
                # TODO Calculate this gz derivative. In some places I see
                # (as_[i] * (1 - as_[i])) instead.
                gz = (1 - np.power(as_[i], 2))
                deltas[i] = deltas[i + 1].dot(self.Ws[i].T) * gz
            except FloatingPointError:
                # Use the max.
                deltas[i] = np.ones(as_[i].shape) * sys.float_info.max

        # Initialise derivatives.
        dWs = [None] * self.num_layers
        dbs = [None] * self.num_layers

        for i in range(self.num_layers - 2, -1, -1):

            # Partial derivative terms.
            dWs[i] = (as_[i].T).dot(deltas[i + 1]) + (self.reg_lambda * self.Ws[i])

            # Sum of the next layer deltas.
            dbs[i] = np.sum(deltas[i + 1], axis=0, keepdims=True)

        return dWs, dbs


    def predict_proba(self, x):

        # Forward propagation
        probs, _, _ = self.forward_prop(x)
        return probs

    def calculate_loss(self, Ws, bs, x, y_):
        probs = self.predict_proba(x)

        # Calculating the loss against the real y values.
        n_examples = len(y_)

        # Calculated probabilities of the correct response being true.
        calculated_y_probs = probs[range(n_examples), y_]

        # Cross-entropy.
        total_data_loss = np.sum(-np.log(calculated_y_probs))

        # Add regularisation to loss.
        weights_squares = 0
        for weights in Ws:
            weights_squares += np.sum(np.square(weights))

        total_data_loss += self.reg_lambda / 2 * weights_squares

        return total_data_loss / n_examples

